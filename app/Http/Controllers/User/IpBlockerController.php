<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\IpBlock;
use App\Services\AgentService;
use Illuminate\Http\Request;

class IpBlockerController extends Controller
{
    /**
     * GET /user/security/ip-blocker
     * Display blocked IPs per domain and form to add new rules.
     */
    public function index(Request $request)
    {
        $accountIds = auth()->user()->currentAccountIds();
        $domains = Domain::with(['account', 'ipBlocks'])
            ->whereIn('account_id', $accountIds)
            ->orderBy('domain')
            ->get();

        $selectedDomainId = $request->get('domain_id', $domains->first()?->id);
        $selectedDomain = $domains->firstWhere('id', $selectedDomainId) ?? $domains->first();

        $blocks = $selectedDomain
            ? $selectedDomain->ipBlocks()->latest()->paginate(25)
            : collect();

        return view('user.security.ip-blocker.index', compact('domains', 'selectedDomain', 'blocks'));
    }

    /**
     * POST /user/security/ip-blocker
     * Add an IP address or CIDR subnet to the block list.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'domain_id'    => 'required|exists:domains,id',
            'ip_or_subnet' => ['required', 'string', 'max:50', 'regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))?$/'],
            'comment'      => 'nullable|string|max:191',
        ]);

        $domain = Domain::with('account.server')->findOrFail($data['domain_id']);
        $this->authorize($domain);

        // Avoid duplicate entry
        $exists = IpBlock::where('domain_id', $domain->id)
            ->where('ip_or_subnet', $data['ip_or_subnet'])
            ->exists();

        if ($exists) {
            return back()->with('error', "IP or Subnet {$data['ip_or_subnet']} is already blocked on {$domain->domain}.");
        }

        IpBlock::create([
            'domain_id'    => $domain->id,
            'account_id'   => $domain->account_id,
            'ip_or_subnet' => $data['ip_or_subnet'],
            'comment'      => $data['comment'] ?? null,
        ]);

        $this->syncWithAgent($domain);

        return back()->with('success', "Blocked IP {$data['ip_or_subnet']} on {$domain->domain}.");
    }

    /**
     * DELETE /user/security/ip-blocker/{ipBlock}
     * Remove an IP block rule.
     */
    public function destroy(IpBlock $ipBlock)
    {
        $domain = $ipBlock->domain()->with('account.server')->firstOrFail();
        $this->authorize($domain);

        $ip = $ipBlock->ip_or_subnet;
        $ipBlock->delete();

        $this->syncWithAgent($domain);

        return back()->with('success', "Unblocked {$ip} from {$domain->domain}.");
    }

    private function syncWithAgent(Domain $domain): void
    {
        $allBlocked = IpBlock::where('domain_id', $domain->id)
            ->pluck('ip_or_subnet')
            ->toArray();

        if ($domain->account && $domain->account->server) {
            AgentService::for($domain->account->server)->post('/security/ip-block-sync', [
                'domain'   => $domain->domain,
                'username' => $domain->account->username,
                'blocked'  => $allBlocked,
            ]);
        }
    }

    private function authorize(Domain $domain): void
    {
        abort_unless(in_array($domain->account_id, auth()->user()->currentAccountIds()), 403);
    }
}
