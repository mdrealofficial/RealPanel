<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use Illuminate\Http\Request;

class CatchallEmailController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::with('account.server')
            ->whereIn('account_id', auth()->user()->currentAccountIds())
            ->where('status', 'active')
            ->get();

        $selectedDomain = null;
        if ($request->has('domain_id')) {
            $selectedDomain = Domain::with('account.server')
                ->whereIn('account_id', auth()->user()->currentAccountIds())
                ->findOrFail($request->domain_id);
        } elseif ($domains->isNotEmpty()) {
            $selectedDomain = $domains->first();
        }

        return view('emails.catchall', compact('domains', 'selectedDomain'));
    }

    public function update(Request $request, Domain $domain)
    {
        $domain->load('account.server');
        abort_unless(in_array($domain->account_id, auth()->user()->currentAccountIds()), 403);

        $validated = $request->validate([
            'catchall_action'      => 'required|in:fail,discard,forward',
            'catchall_destination' => 'nullable|email|required_if:catchall_action,forward',
        ]);

        $response = AgentService::for($domain->account->server)->post('/email/catchall-set', [
            'domain'      => $domain->domain,
            'action'      => $validated['catchall_action'],
            'destination' => $validated['catchall_destination'] ?? '',
        ]);

        if ($response && $response->successful()) {
            $domain->update([
                'catchall_action'      => $validated['catchall_action'],
                'catchall_destination' => $validated['catchall_destination'] ?? null,
            ]);

            ActivityLogger::log('email.catchall_updated', 'domain', $domain->id, $domain->domain,
                "Updated catch-all for {$domain->domain} to {$validated['catchall_action']}",
                ['domain_id' => $domain->id]
            );

            return back()->with('success', "Catch-all configuration for {$domain->domain} updated.");
        }

        $error = $response ? $response->json('error', 'Unknown agent error') : 'Could not contact agent';
        return back()->with('error', 'Failed to update catch-all configuration: ' . $error);
    }
}
