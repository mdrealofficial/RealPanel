<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\Server;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        $servers = Server::all();
        $selectedServer = null;
        $backups = collect();
        $accounts = collect();

        if ($request->has('server_id')) {
            $selectedServer = Server::findOrFail($request->server_id);
        } elseif ($servers->count() === 1) {
            $selectedServer = $servers->first();
        }

        if ($selectedServer) {
            $backups = Backup::where('server_id', $selectedServer->id)->latest()->get();
            $accounts = Account::where('server_id', $selectedServer->id)->get();

            // Sync with agent's backup list
            $response = AgentService::for($selectedServer)->post('/backup/list', []);
            if ($response && $response->successful()) {
                $agentBackups = (array) ($response->json('backups') ?? []);
                foreach ($agentBackups as $ab) {
                    if (!is_array($ab) || empty($ab['filename'])) {
                        continue;
                    }
                    if (!$backups->where('filename', $ab['filename'])->first()) {
                        Backup::create([
                            'server_id' => $selectedServer->id,
                            'username'  => (string) ($ab['username'] ?? ''),
                            'filename'  => (string) $ab['filename'],
                            'type'      => (string) ($ab['type'] ?? 'full'),
                            'size_mb'   => round((float) ($ab['size_mb'] ?? 0), 2),
                            'status'    => 'completed',
                        ]);
                    }
                }
            }
        }

        $destinations = $selectedServer
            ? BackupDestination::where('server_id', $selectedServer->id)->latest()->get()
            : collect();

        return view('backups.index', compact('servers', 'selectedServer', 'backups', 'accounts', 'destinations'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'server_id'  => 'required|exists:servers,id',
            'account_id' => 'required|exists:accounts,id',
            'type'       => 'required|in:full,files,databases,email',
        ]);

        $server = Server::findOrFail($validated['server_id']);
        $account = Account::with('domains', 'databases')->findOrFail($validated['account_id']);

        $jobId = 'bk_' . $account->username . '_' . time();

        $response = AgentService::for($server)->post('/backup/create', [
            'job_id'    => $jobId,
            'username'  => $account->username,
            'type'      => $validated['type'],
            'databases' => $account->databases->pluck('name')->toArray(),
            'domains'   => $account->domains->pluck('domain')->toArray(),
            'async'     => true,
        ]);

        if ($response && $response->successful()) {
            ActivityLogger::log('backup.started', 'account', $account->id, $account->username,
                "Started {$validated['type']} backup for {$account->username} in background");

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status'   => 'started',
                    'job_id'   => $jobId,
                    'username' => $account->username,
                    'message'  => 'Backup process started in the background.',
                    'progress' => 5,
                    'step'     => 'Initializing backup...',
                ]);
            }

            return redirect()->route('admin.backups.index', ['server_id' => $server->id])
                ->with('success', 'Backup process started in the background.');
        }

        $error = $response ? ($response->json('error') ?? $response->json('message') ?? 'Unknown error') : 'Agent unreachable';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => 'error', 'error' => $error], 500);
        }
        return back()->with('error', __('backups.backup_failed', ['error' => $error]));
    }

    /**
     * GET /admin/backups/progress — poll progress of a background backup job
     */
    public function progress(Request $request)
    {
        $serverId = $request->query('server_id');
        $server = $serverId ? Server::find($serverId) : Server::first();
        if (!$server) {
            return response()->json(['status' => 'error', 'error' => 'Server not found'], 404);
        }

        $jobId = $request->query('job_id', '');
        $username = $request->query('username', '');

        try {
            $url = '/backup/progress?id=' . urlencode($jobId) . '&username=' . urlencode($username);
            $response = AgentService::for($server)->get($url);
            if ($response && $response->successful()) {
                $data = $response->json();
                $job = $data['job'] ?? null;

                // If completed and not yet in database, register it
                if ($job && ($job['status'] ?? '') === 'completed' && !empty($job['filename'])) {
                    if (!Backup::where('filename', $job['filename'])->exists()) {
                        $acc = Account::where('username', $job['username'])->first();
                        Backup::create([
                            'server_id'  => $server->id,
                            'account_id' => $acc?->id,
                            'username'   => $job['username'] ?? '',
                            'filename'   => $job['filename'],
                            'type'       => 'full',
                            'size_mb'    => round((float) ($job['size_mb'] ?? 0), 2),
                            'status'     => 'completed',
                        ]);
                    }
                }

                return response()->json($data);
            }
        } catch (\Exception $e) {}

        return response()->json([
            'status' => 'idle',
            'job' => [
                'status'   => 'idle',
                'progress' => 0,
                'step'     => 'No active backup job',
            ],
        ]);
    }

    public function restore(Request $request, Backup $backup)
    {
        $backup->load('server');

        $response = AgentService::for($backup->server)->postLong('/backup/restore', [
            'filename' => $backup->filename,
            'username' => $backup->username,
            'type'     => $request->input('type', 'full'),
        ], 600);

        if ($response && $response->successful()) {
            $restored = (array) ($response->json('restored') ?? ['all components']);
            ActivityLogger::log('backup.restored', 'account', $backup->account_id, $backup->username,
                "Restored backup {$backup->filename}: " . implode(', ', $restored));

            return redirect()->route('admin.backups.index', ['server_id' => $backup->server_id])
                ->with('success', __('backups.restore_successful', ['items' => implode(', ', $restored)]));
        }

        $error = $response ? ($response->json('error') ?? $response->json('message') ?? 'Unknown error') : 'Agent unreachable';
        return back()->with('error', __('backups.restore_failed', ['error' => $error]));
    }

    public function download(Backup $backup)
    {
        $backup->load('server');
        $downloadUrl = rtrim($backup->server->agent_url, '/') . '/backup/download?filename=' . urlencode($backup->filename);
        return redirect($downloadUrl);
    }

    public function destroy(Backup $backup)
    {
        $backup->load('server');

        AgentService::for($backup->server)->post('/backup/delete', [
            'filename' => $backup->filename,
        ]);

        ActivityLogger::log('backup.deleted', 'account', $backup->account_id, $backup->username,
            "Deleted backup {$backup->filename}");

        $backup->delete();

        return redirect()->route('admin.backups.index', ['server_id' => $backup->server_id])
            ->with('success', __('backups.backup_deleted'));
    }

    /**
     * GET /admin/backups/{backup}/browse?path=...
     * Show the file browser for a single backup at the given path.
     */
    public function browse(Request $request, Backup $backup)
    {
        $backup->load('server');

        $path = $request->input('path', '');

        $response = AgentService::for($backup->server)->post('/backup/browse', [
            'filename' => $backup->filename,
            'path'     => $path,
        ]);

        $entries = [];
        $error   = null;

        if ($response && $response->successful()) {
            $entries = (array) ($response->json('entries') ?? []);
        } else {
            $error = $response?->json('error') ?? $response?->json('message') ?? 'Agent unreachable';
        }

        // Build breadcrumbs
        $crumbs = [];
        if ($path !== '') {
            $accumulated = '';
            foreach (explode('/', trim($path, '/')) as $segment) {
                if ($segment === '') continue;
                $accumulated = ($accumulated === '' ? $segment : "$accumulated/$segment");
                $crumbs[] = ['name' => $segment, 'path' => $accumulated . '/'];
            }
        }

        return view('backups.browse', compact('backup', 'entries', 'path', 'crumbs', 'error'));
    }

    /**
     * POST /admin/backups/{backup}/restore-files
     * Restore individual files from a backup. Files come from a checkbox list
     * in the browse view.
     */
    public function restoreFiles(Request $request, Backup $backup)
    {
        $data = $request->validate([
            'files'         => 'required|array|min:1|max:200',
            'files.*'       => 'string|max:1000',
            'document_root' => 'required|string|max:255',
            'overwrite'     => 'nullable|boolean',
        ]);

        $backup->load('server');

        $response = AgentService::for($backup->server)->postLong('/backup/restore-files', [
            'filename'      => $backup->filename,
            'username'      => $backup->username,
            'document_root' => $data['document_root'],
            'files'         => $data['files'],
            'overwrite'     => (bool) ($data['overwrite'] ?? false),
        ], 600);

        if (! $response || ! $response->successful()) {
            $error = $response?->json('error') ?? $response?->json('message') ?? 'Agent unreachable';
            return back()->with('error', "Restore failed: {$error}");
        }

        $result = (array) $response->json();
        $restored = (int) ($result['restored'] ?? 0);
        $skipped  = (int) ($result['skipped'] ?? 0);

        ActivityLogger::log('backup.restored_files', 'account', $backup->account_id, $backup->username,
            "Restored {$restored} file(s) from backup {$backup->filename}", ['files' => $data['files']]);

        $msg = "Restored {$restored} file(s).";
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} (already existed — tick 'overwrite' to replace).";
        }

        return back()->with('success', $msg);
    }

    /**
     * Store new remote cloud backup destination (S3, B2, SFTP, GDrive)
     */
    public function storeDestination(Request $request)
    {
        $validated = $request->validate([
            'server_id'       => 'required|exists:servers,id',
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:s3,b2,sftp,gdrive',
            'bucket'          => 'nullable|string|max:150',
            'remote_path'     => 'nullable|string|max:255',
            'retention_count' => 'required|integer|min:1|max:90',
            'credentials'     => 'required|array',
        ]);

        $server = Server::findOrFail($validated['server_id']);

        // Test with agent first
        $res = AgentService::for($server)->post('/backup/cloud-test', [
            'type'        => $validated['type'],
            'credentials' => $validated['credentials'],
        ]);

        if (!$res || !$res->successful()) {
            $err = $res ? $res->json('error', 'Agent error') : 'Server agent unreachable';
            return back()->with('error', 'Destination validation failed: ' . $err);
        }

        BackupDestination::create([
            'server_id'             => $server->id,
            'name'                  => $validated['name'],
            'type'                  => $validated['type'],
            'credentials_encrypted' => $validated['credentials'],
            'bucket'                => $validated['bucket'],
            'remote_path'           => $validated['remote_path'],
            'retention_count'       => $validated['retention_count'],
            'status'                => 'active',
            'last_tested_at'        => now(),
        ]);

        return back()->with('success', "Cloud backup destination \"{$validated['name']}\" saved and verified.");
    }

    /**
     * Test an existing cloud backup destination
     */
    public function testDestination(BackupDestination $destination)
    {
        $destination->load('server');

        $res = AgentService::for($destination->server)->post('/backup/cloud-test', [
            'type'        => $destination->type,
            'credentials' => $destination->credentials_encrypted ?? [],
        ]);

        if (!$res || !$res->successful()) {
            $err = $res ? $res->json('error', 'Connection test failed') : 'Agent unreachable';
            $destination->update(['status' => 'error']);
            return back()->with('error', "Destination \"{$destination->name}\" connection test failed: {$err}");
        }

        $destination->update(['status' => 'active', 'last_tested_at' => now()]);
        return back()->with('success', "Connection to \"{$destination->name}\" tested successfully.");
    }

    /**
     * Delete a cloud backup destination
     */
    public function destroyDestination(BackupDestination $destination)
    {
        $destination->delete();
        return back()->with('success', 'Backup destination removed.');
    }

    /**
     * Sync local backup archive to remote cloud destination
     */
    public function syncCloud(Request $request, Backup $backup)
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:backup_destinations,id',
        ]);

        $destination = BackupDestination::with('server')->findOrFail($validated['destination_id']);
        $backup->load('server');

        $res = AgentService::for($backup->server)->post('/backup/cloud-sync', [
            'filename'    => $backup->filename,
            'type'        => $destination->type,
            'bucket'      => $destination->bucket,
            'remote_path' => $destination->remote_path,
            'credentials' => $destination->credentials_encrypted ?? [],
            'retention'   => $destination->retention_count,
        ]);

        if (!$res || !$res->successful()) {
            $err = $res ? $res->json('error', 'Sync failed') : 'Agent unreachable';
            return back()->with('error', 'Cloud sync failed: ' . $err);
        }

        $destination->update(['last_sync_at' => now()]);
        ActivityLogger::log('backup.cloud_sync', 'backup', $backup->id, $backup->filename, "Synced backup {$backup->filename} to {$destination->name}");

        return back()->with('success', "Backup \"{$backup->filename}\" successfully offloaded to {$destination->name}.");
    }
}
