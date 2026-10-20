<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AgentService;
use Illuminate\Http\Request;

class DiskUsageController extends Controller
{
    /**
     * Show interactive visual disk space breakdown for current account
     */
    public function index(Request $request)
    {
        $account = auth()->user()->currentAccount();
        if (!$account) {
            return redirect()->route('user.dashboard')->with('error', 'No active account selected.');
        }

        $account->load('server', 'domains');
        $domains = $account->domains->pluck('domain')->toArray();

        $data = [
            'total_bytes'    => 0,
            'total_mb'       => 0,
            'home_bytes'     => 0,
            'mail_bytes'     => 0,
            'database_bytes' => 0,
            'breakdown'      => [],
        ];

        $res = AgentService::for($account->server)->post('/files/disk-breakdown', [
            'username' => $account->username,
            'domains'  => $domains,
        ]);

        if ($res && $res->successful()) {
            $data = array_merge($data, $res->json());
        }

        $quotaMB = $account->disk_quota_mb ?? 10240; // Default 10GB if unmetered

        return view('files.disk_usage', compact('account', 'data', 'quotaMB'));
    }

    /**
     * Execute instant disk cleanup actions (empty trash, clear temporary logs, clear tmp)
     */
    public function clean(Request $request)
    {
        $account = auth()->user()->currentAccount();
        if (!$account) {
            return back()->with('error', 'No active account.');
        }

        $validated = $request->validate([
            'action' => 'required|in:empty_trash,clear_logs,clear_tmp',
        ]);

        $res = AgentService::for($account->server)->post('/files/disk-clean', [
            'username' => $account->username,
            'action'   => $validated['action'],
        ]);

        if ($res && $res->successful()) {
            return back()->with('success', $res->json('message', 'Cleanup completed successfully.'));
        }

        $err = $res ? $res->json('error', 'Cleanup error') : 'Agent unreachable';
        return back()->with('error', 'Cleanup failed: ' . $err);
    }
}
