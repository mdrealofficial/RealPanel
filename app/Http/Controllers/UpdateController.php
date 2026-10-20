<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UpdateController extends Controller
{
    public function index()
    {
        $currentVersion = config('realpanel.version', '1.0.0');

        // Check for latest version from GitHub repository
        $latestVersion = null;
        $updateUrl = config('realpanel.update_version_url', 'https://raw.githubusercontent.com/mdrealofficial/RealPanel/main/version.txt');
        $repo = config('realpanel.github_repo', 'mdrealofficial/RealPanel');

        try {
            $response = Http::timeout(5)->get($updateUrl);
            if ($response->successful()) {
                $latestVersion = trim($response->body());
            }
        } catch (\Exception $e) {
            // Raw file fetch failed — fallback to GitHub Releases API
        }

        if (! $latestVersion) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'RealPanel-UpdateCheck'])
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");
                if ($response->successful()) {
                    $tag = $response->json('tag_name');
                    $latestVersion = ltrim($tag, 'v');
                }
            } catch (\Exception $e) {}
        }

        // Read release notes for the current version from CHANGELOG.md in the panel root.
        $changelog = $this->readChangelogFor($currentVersion);

        $updateAvailable = $latestVersion && version_compare($latestVersion, $currentVersion, '>');

        // Get agent version + update log from each server
        $servers = Server::all();
        $agentVersion = null;
        $mailVersion  = null;
        $updateLog    = null;

        foreach ($servers as $server) {
            try {
                $versionResp = AgentService::for($server)->get('/version');
                if ($versionResp && $versionResp->successful()) {
                    $agentVersion  = $versionResp->json('agent_version');
                    $mailInstalled = (bool) $versionResp->json('mail_installed', false);
                    $mailVersion   = $versionResp->json('mail_version') ?: null;
                }

                $logResp = AgentService::for($server)->get('/update/log');
                if ($logResp && $logResp->successful()) {
                    $updateLog = $logResp->json('log', '');
                }
            } catch (\Exception $e) {}

            // Only need the first server for now
            break;
        }

        $mailInstalled = $mailInstalled ?? false;
        return view('admin.updates', compact(
            'currentVersion', 'latestVersion', 'changelog',
            'updateAvailable', 'agentVersion', 'mailInstalled', 'mailVersion', 'updateLog'
        ));
    }

    /**
     * Run the panel update script via the agent.
     */
    public function run(Request $request)
    {
        $server = Server::first();
        if (!$server) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'error' => __('servers.no_server_configured')], 404);
            }
            return back()->with('error', __('servers.no_server_configured'));
        }

        $response = AgentService::for($server)->post('/update/run', []);

        if ($response && $response->successful()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status'   => 'started',
                    'message'  => __('servers.update_started') ?: 'Update started in background',
                    'progress' => $response->json('progress', 5),
                    'step'     => $response->json('step', 'Initializing update...'),
                ]);
            }
            return redirect()->route('admin.updates.index')->with('success', 'Update started in the background.');
        }

        $error  = $response ? $response->json('error', 'Unknown error') : 'Could not connect to agent';
        $output = $response ? $response->json('output', '') : '';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => 'error', 'error' => $error, 'output' => $output], 500);
        }

        return back()->with('error', __('servers.update_failed', ['error' => $error]))->with('output', $output);
    }

    /**
     * GET /admin/updates/status — returns live background update status & progress.
     */
    public function status()
    {
        $server = Server::first();
        if (!$server) {
            return response()->json(['status' => 'error', 'error' => 'No server configured'], 404);
        }

        try {
            $response = AgentService::for($server)->get('/update/status');
            if ($response && $response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Exception $e) {}

        return response()->json([
            'status'   => 'idle',
            'running'  => false,
            'progress' => 0,
            'step'     => 'Idle',
        ]);
    }

    /**
     * GET /admin/updates/log — returns the update log as JSON for the live log viewer.
     */
    public function log()
    {
        $server = Server::first();
        $log = '';

        if ($server) {
            try {
                $response = AgentService::for($server)->get('/update/log');
                if ($response && $response->successful()) {
                    $log = $response->json('log', '');
                }
            } catch (\Exception $e) {}
        }

        return response()->json(['log' => $log]);
    }

    /**
     * Extract the section for a given version from CHANGELOG.md.
     * Matches headings like "## [2.2.3] - 2026-04-21" and returns all content
     * until the next "## " heading. Returns null if the file or section is missing.
     */
    private function readChangelogFor(string $version): ?string
    {
        $path = base_path('CHANGELOG.md');
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $escaped = preg_quote($version, '/');
        if (!preg_match('/^##\s*\[' . $escaped . '\][^\n]*\n(.*?)(?=^##\s|\z)/ms', $content, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * Force an immediate webmail (RealPanel Mail) git pull + migrate via the agent.
     */
    public function forceMailUpdate(Request $request)
    {
        $server = Server::first();
        if (!$server) {
            return back()->with('error', __('servers.no_server_configured'));
        }

        $response = AgentService::for($server)->post('/update/mail', []);

        if ($response && $response->successful()) {
            $status = $response->json('status');
            if ($status === 'skipped') {
                return redirect()->route('admin.updates.index')
                    ->with('success', 'Webmail is not installed on the server — nothing to update.');
            }
            return redirect()->route('admin.updates.index')
                ->with('success', 'Webmail updated successfully.');
        }

        $error = $response ? $response->json('error', 'Unknown error') : 'Could not connect to agent';
        return back()->with('error', "Webmail update failed: {$error}");
    }

    /**
     * Tell the agent to check for a new binary immediately (force update).
     */
    public function forceAgentUpdate(Request $request)
    {
        $server = Server::first();
        if (!$server) {
            return back()->with('error', __('servers.no_server_configured'));
        }

        $response = AgentService::for($server)->post('/update/agent', []);

        if ($response && $response->successful()) {
            return redirect()->route('admin.updates.index')
                ->with('success', 'Agent update check triggered. The log below will update in a few seconds.');
        }

        $error = $response ? $response->json('error', 'Unknown error') : 'Could not connect to agent';
        return back()->with('error', "Agent update failed: {$error}");
    }
}
