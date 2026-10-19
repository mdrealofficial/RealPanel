<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminTerminalController extends Controller
{
    /**
     * Show the Root Terminal session page.
     * Only full administrators can access this.
     */
    public function index()
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized. Root terminal access is restricted to administrators.');
        }

        // Get the primary local server (or default server)
        $server = Server::where('is_local', true)->first() ?? Server::first();

        // Terminal path configured via Nginx reverse proxy
        $terminalUrl = '/terminal-root/';

        return view('admin.terminal.index', [
            'terminalUrl' => $terminalUrl,
            'server'      => $server,
        ]);
    }

    /**
     * Nginx auth_request callback to verify if the client has an active admin session.
     * Returns 200 OK if authenticated as admin, 401 Unauthorized otherwise.
     */
    public function checkAuth(Request $request)
    {
        if ($request->user() && $request->user()->isAdmin()) {
            return response('OK', 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Unauthorized', 401)
            ->header('Content-Type', 'text/plain');
    }
}
