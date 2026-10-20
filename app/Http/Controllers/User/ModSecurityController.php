<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\AgentService;
use Illuminate\Http\Request;

class ModSecurityController extends Controller
{
    /**
     * GET /user/security/modsecurity
     * Display ModSecurity status and rule exceptions per domain.
     */
    public function index(Request $request)
    {
        $accountIds = auth()->user()->currentAccountIds();
        $domains = Domain::with(['account.server'])
            ->whereIn('account_id', $accountIds)
            ->orderBy('domain')
            ->get();

        $selectedDomainId = $request->get('domain_id', $domains->first()?->id);
        $selectedDomain = $domains->firstWhere('id', $selectedDomainId) ?? $domains->first();

        $modsecInfo = [
            'installed'      => false,
            'enabled'        => false,
            'disabled_rules' => [],
        ];

        if ($selectedDomain && $selectedDomain->account && $selectedDomain->account->server) {
            $resp = AgentService::for($selectedDomain->account->server)->post('/security/modsec/status', [
                'domain' => $selectedDomain->domain,
            ]);
            if ($resp && $resp->successful()) {
                $modsecInfo = $resp->json();
            }
        }

        return view('user.security.modsecurity.index', compact('domains', 'selectedDomain', 'modsecInfo'));
    }

    /**
     * POST /user/security/modsecurity/{domain}/toggle
     * Enable or disable ModSecurity WAF for this domain.
     */
    public function toggle(Request $request, Domain $domain)
    {
        $this->authorize($domain);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        if ($domain->account && $domain->account->server) {
            $resp = AgentService::for($domain->account->server)->post('/security/modsec/toggle', [
                'domain'  => $domain->domain,
                'enabled' => (bool) $validated['enabled'],
            ]);

            if ($resp && $resp->successful()) {
                $statusMsg = $validated['enabled'] ? 'enabled' : 'disabled';
                return back()->with('success', "ModSecurity WAF {$statusMsg} for {$domain->domain}.");
            }
        }

        return back()->with('error', "Failed to update ModSecurity state on server.");
    }

    /**
     * POST /user/security/modsecurity/{domain}/whitelist
     * Add or remove a ModSecurity rule exception.
     */
    public function whitelistRule(Request $request, Domain $domain)
    {
        $this->authorize($domain);

        $validated = $request->validate([
            'rule_id' => 'required|numeric',
            'action'  => 'required|in:add,remove',
        ]);

        if ($domain->account && $domain->account->server) {
            $resp = AgentService::for($domain->account->server)->post('/security/modsec/whitelist', [
                'domain'  => $domain->domain,
                'rule_id' => (string) $validated['rule_id'],
                'action'  => $validated['action'],
            ]);

            if ($resp && $resp->successful()) {
                $actionWord = $validated['action'] === 'remove' ? 're-enabled' : 'whitelisted (bypassed)';
                return back()->with('success', "Rule ID {$validated['rule_id']} {$actionWord} for {$domain->domain}.");
            }
        }

        return back()->with('error', "Failed to modify ModSecurity whitelist rule.");
    }

    private function authorize(Domain $domain): void
    {
        abort_unless(in_array($domain->account_id, auth()->user()->currentAccountIds()), 403);
    }
}
