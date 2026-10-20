<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\EmailAccount;
use App\Services\AgentService;
use Illuminate\Http\Request;

class EmailDiskUsageController extends Controller
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
                ->find($request->domain_id);
        }
        if (!$selectedDomain && $domains->isNotEmpty()) {
            $selectedDomain = $domains->first();
        }

        $mailboxes = [];
        if ($selectedDomain) {
            $response = AgentService::for($selectedDomain->account->server)->post('/email/disk-usage', [
                'domain' => $selectedDomain->domain,
            ]);
            if ($response && $response->successful()) {
                $mailboxes = $response->json('mailboxes', []);
            }
        }

        return view('emails.disk_usage', compact('domains', 'selectedDomain', 'mailboxes'));
    }

    public function clean(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'folder'   => 'required|string',
            'days_old' => 'required|integer|in:30,60,90,180,365',
        ]);

        $parts = explode('@', $validated['email']);
        $domainStr = $parts[1] ?? '';
        $domain = Domain::with('account.server')
            ->whereIn('account_id', auth()->user()->currentAccountIds())
            ->where('domain', $domainStr)
            ->firstOrFail();

        $response = AgentService::for($domain->account->server)->post('/email/clean-messages', [
            'email'    => $validated['email'],
            'folder'   => $validated['folder'],
            'days_old' => (int) $validated['days_old'],
        ]);

        if ($response && $response->successful()) {
            return back()->with('success', "Cleaned messages in folder {$validated['folder']} older than {$validated['days_old']} days for {$validated['email']}.");
        }

        $error = $response ? $response->json('error', 'Cleanup failed') : 'Could not contact server agent';
        return back()->with('error', $error);
    }
}
