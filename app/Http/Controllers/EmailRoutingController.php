<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use Illuminate\Http\Request;

class EmailRoutingController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::with('account.server')
            ->whereIn('account_id', auth()->user()->currentAccountIds())
            ->where('status', 'active')
            ->get();

        $selectedDomain = null;
        $mxRecords = [];

        if ($request->has('domain_id')) {
            $selectedDomain = Domain::with('account.server')
                ->whereIn('account_id', auth()->user()->currentAccountIds())
                ->findOrFail($request->domain_id);
        } elseif ($domains->isNotEmpty()) {
            $selectedDomain = $domains->first();
        }

        if ($selectedDomain) {
            // Fetch live MX records via dns_get_record
            $records = @dns_get_record($selectedDomain->domain, DNS_MX);
            if ($records) {
                foreach ($records as $r) {
                    $mxRecords[] = [
                        'target' => $r['target'] ?? '',
                        'pri'    => $r['pri'] ?? 10,
                    ];
                }
            }
        }

        return view('emails.routing', compact('domains', 'selectedDomain', 'mxRecords'));
    }

    public function update(Request $request, Domain $domain)
    {
        $domain->load('account.server');
        abort_unless(in_array($domain->account_id, auth()->user()->currentAccountIds()), 403);

        $validated = $request->validate([
            'mail_routing' => 'required|in:local,remote,backup,auto',
        ]);

        $routing = $validated['mail_routing'];
        $resolvedRouting = $routing;

        if ($routing === 'auto') {
            // Detect based on MX records
            $records = @dns_get_record($domain->domain, DNS_MX);
            $hasLocal = false;
            if ($records && $domain->server) {
                foreach ($records as $r) {
                    $target = $r['target'] ?? '';
                    $ips = @dns_get_record($target, DNS_A);
                    if ($ips) {
                        foreach ($ips as $ip) {
                            if (($ip['ip'] ?? '') === $domain->server->ip_address) {
                                $hasLocal = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            $resolvedRouting = $hasLocal ? 'local' : 'remote';
        }

        // Send to agent
        $response = AgentService::for($domain->account->server)->post('/email/routing-set', [
            'domain'  => $domain->domain,
            'routing' => $resolvedRouting,
        ]);

        if ($response && $response->successful()) {
            $domain->update(['mail_routing' => $routing]);

            ActivityLogger::log('email.routing_updated', 'domain', $domain->id, $domain->domain,
                "Updated mail routing for {$domain->domain} to {$routing} (resolved: {$resolvedRouting})",
                ['domain_id' => $domain->id]
            );

            return back()->with('success', "Email routing for {$domain->domain} updated to " . ucfirst($routing) . ($routing === 'auto' ? " (detected: " . ucfirst($resolvedRouting) . ")" : ""));
        }

        $error = $response ? $response->json('error', 'Unknown agent error') : 'Could not contact agent';
        return back()->with('error', 'Failed to update email routing: ' . $error);
    }
}
