<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\AgentService;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;

class DomainController extends Controller
{
    public function index()
    {
        // Only show main domains (not subdomains) — each account has exactly one main domain
        $domains = Domain::with('server', 'account', 'sslCertificate', 'subdomains')
            ->whereIn('account_id', auth()->user()->currentAccountIds())
            ->whereNull('parent_id')
            ->latest()
            ->get();

        // Annotate each domain with `wildcard_active` so the catch-all card
        // can give the right SSL warning. The cert record's `type` is set
        // when the wildcard is first issued, but switches to 'letsencrypt'
        // the moment the apex gets its own HTTP-01 cert — so we can't trust
        // it alone. Asking the agent is authoritative: it checks the
        // wildcard cert file on disk.
        foreach ($domains as $d) {
            $d->wildcard_active = false;
            if ($d->server) {
                $resp = AgentService::for($d->server)->post('/ssl/status', ['domain' => $d->domain]);
                if ($resp && $resp->successful()) {
                    $d->wildcard_active = (bool) $resp->json('wildcard_exists', false);
                }
            }
        }

        return view('domains.index', compact('domains'));
    }

    public function create()
    {
        $accounts = auth()->user()->scopedToCurrent()
            ->with('server')
            ->get();

        return view('domains.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $phpVersions = config('realpanel.php_versions') ?? ['8.1', '8.2', '8.3', '8.4'];

        $validated = $request->validate([
            'account_id'  => 'required|exists:accounts,id',
            'domain'      => 'required|string|max:255|unique:domains,domain',
            'php_version' => 'required|string|in:' . implode(',', $phpVersions),
        ]);

        $account = auth()->user()->scopedToCurrent()
            ->with(['server', 'package'])
            ->findOrFail($validated['account_id']);

        if (!$account->userCan(auth()->user(), 'settings')) {
            abort(403, 'Unauthorized.');
        }

        $server = $account->server;
        $documentRoot = $account->home_directory . '/' . $validated['domain'] . '/public_html';

        $domain = Domain::create([
            'server_id'     => $server->id,
            'account_id'    => $account->id,
            'domain'        => $validated['domain'],
            'document_root' => $documentRoot,
            'php_version'   => $validated['php_version'],
            'status'        => 'pending',
        ]);

        // Tell the agent to create the domain on the server
        $response = AgentService::for($server)->post('/domains/create', [
            'domain'        => $domain->domain,
            'document_root' => $domain->document_root,
            'username'      => $account->username,
            'php_version'   => $domain->php_version,
        ]);

        if ($response && $response->successful()) {
            $domain->update(['status' => 'active']);

            // Auto-create DNS zone using assigned package template or default template
            $dnsTemplate = null;
            if ($account->package && $account->package->dns_template_id) {
                $dnsTemplate = \App\Models\DnsTemplate::find($account->package->dns_template_id);
            }
            if (!$dnsTemplate) {
                $dnsTemplate = \App\Models\DnsTemplate::where('is_default', true)->first();
            }

            $resolvedRecords = $dnsTemplate ? $dnsTemplate->resolveRecords($domain->domain, $server->ip_address) : [];

            AgentService::for($server)->post('/dns/create-zone', [
                'domain'    => $domain->domain,
                'server_ip' => $server->ip_address,
                'ns1'       => \App\Models\Setting::getNs1($domain->domain),
                'ns2'       => \App\Models\Setting::getNs2($domain->domain),
                'records'   => $resolvedRecords,
            ]);

            // Auto-issue SSL certificate
            \App\Http\Controllers\SslController::autoIssue($domain);

            ActivityLogger::log(
                'domain.created',
                'domain',
                $domain->id,
                $domain->domain,
                "Added domain {$domain->domain} to account {$account->username}",
                ['account_id' => $account->id, 'server_id' => $server->id]
            );

            return redirect()->route('user.domains.index')->with('success', __('domains.domain_created', ['domain' => $domain->domain]));
        }

        $error = $response ? $response->json('error', 'Unknown error') : 'Could not connect to server agent';
        $domain->update(['status' => 'error']);

        return redirect()->route('user.domains.index')->with('warning', 'Domain saved but agent setup failed: ' . $error);
    }

    public function destroy(Request $request, Domain $domain)
    {
        $domain->load('account');

        if (!$domain->account->userCan(auth()->user(), 'settings')) {
            return back()->with('error', __('domains.no_permission_delete'));
        }

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => __('common.password_incorrect')]);
        }

        // Only allow deleting subdomains, not the main domain (main domain is deleted with account)
        if (!$domain->isSubdomain()) {
            return back()->with('error', __('domains.main_domain_cannot_be_deleted'));
        }

        $domain->load('account.server');

        ActivityLogger::log('subdomain.deleted', 'domain', $domain->id, $domain->domain,
            "Deleted subdomain {$domain->domain}", ['server_id' => $domain->server_id, 'account_id' => $domain->account_id]);

        // Send delete request to the Go agent
        AgentService::for($domain->account->server)->post('/domains/delete', [
            'domain'      => $domain->domain,
            'username'    => $domain->account->username,
            'php_version' => $domain->php_version,
        ]);

        $domain->delete();

        return redirect()->route('user.domains.index')->with('success', __('domains.subdomain_deleted', ['domain' => $domain->domain]));
    }

    /**
     * POST /user/domains/{domain}/catchall
     *
     * Toggles multi-tenant catch-all mode: when enabled, every *.{domain}
     * subdomain serves the same nginx vhost without needing a per-subdomain
     * record. Useful for SaaS where customers get acmeN.fastbiz.ro on signup.
     * Pairs with a wildcard SSL cert on the same domain.
     */
    public function toggleCatchall(Request $request, Domain $domain)
    {
        $domain->load('account.server');

        if (!$domain->account->userCan(auth()->user(), 'settings')) {
            return back()->with('error', __('domains.no_permission'));
        }

        if ($domain->isSubdomain()) {
            return back()->with('error', 'Catch-all only applies to main domains.');
        }

        $enabled = (bool) $request->boolean('enabled');

        // Optional custom doc root — NULL means inherit from main domain vhost.
        $docRoot = trim((string) $request->input('document_root', ''));
        if ($enabled && $docRoot !== '' && !str_starts_with($docRoot, '/')) {
            return back()->with('error', 'Document root must be an absolute path (start with /).');
        }

        $response = AgentService::for($domain->account->server)->post('/domain/catchall', [
            'domain'        => $domain->domain,
            'username'      => $domain->account->username,
            'enabled'       => $enabled,
            'document_root' => $docRoot,
            'php_version'   => $domain->php_version,
        ]);

        if (!$response || !$response->successful()) {
            $error = $response ? $response->json('error', 'Unknown error') : 'Could not reach agent';
            return back()->with('error', "Catch-all toggle failed: {$error}");
        }

        $domain->update([
            'catchall_subdomains'     => $enabled,
            'catchall_document_root'  => $enabled && $docRoot !== '' ? $docRoot : null,
        ]);

        ActivityLogger::log('domain.catchall', 'domain', $domain->id, $domain->domain,
            ($enabled ? 'Enabled' : 'Disabled') . " catch-all subdomains for {$domain->domain}",
            ['enabled' => $enabled]);

        $msg = $enabled
            ? "Catch-all enabled — any *.{$domain->domain} subdomain now serves the same site."
            : "Catch-all disabled — subdomains again require their own record.";
        return back()->with('success', $msg);
    }
}
