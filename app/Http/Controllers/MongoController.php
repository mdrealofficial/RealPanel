<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MongoDatabase;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MongoController extends Controller
{
    public function index()
    {
        $databases = MongoDatabase::with('account.server')
            ->whereIn('account_id', auth()->user()->currentAccountIds())
            ->latest()
            ->get();

        return view('mongodb.index', compact('databases'));
    }

    public function create()
    {
        $accounts = auth()->user()->scopedToCurrent()
            ->with('server')
            ->get();

        return view('mongodb.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'db_name'    => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
        ], [
            'db_name.regex' => 'Database name can only contain letters, numbers, and underscores.',
        ]);

        $account = auth()->user()->scopedToCurrent()
            ->with('server')
            ->findOrFail($validated['account_id']);

        abort_unless(in_array($account->id, auth()->user()->currentAccountIds()), 403);

        // Prefix with account username (cPanel-style uniqueness)
        $dbName    = $account->prefixDbIdentifier($validated['db_name']);
        $mongoUser = $account->prefixDbIdentifier($validated['db_name'] . '_u');
        $password  = Str::random(24);

        // 1. Create MongoDB database
        $response = AgentService::for($account->server)->post('/mongodb/create', [
            'name' => $dbName,
        ]);

        if (!$response || !$response->successful()) {
            $error = $response ? $response->json('error', 'Unknown error') : 'Could not connect to agent';
            return back()->with('error', 'Failed to create MongoDB database: ' . $error)->withInput();
        }

        // 2. Create MongoDB user with readWrite privileges scoped to database
        $response = AgentService::for($account->server)->post('/mongodb/user-create', [
            'username' => $mongoUser,
            'password' => $password,
            'database' => $dbName,
        ]);

        if (!$response || !$response->successful()) {
            // Roll back database
            AgentService::for($account->server)->post('/mongodb/delete', ['name' => $dbName]);
            $error = $response ? $response->json('error', 'Unknown error') : 'Could not connect to agent';
            return back()->with('error', 'Failed to create MongoDB user: ' . $error)->withInput();
        }

        $db = MongoDatabase::create([
            'server_id'      => $account->server_id,
            'account_id'     => $account->id,
            'name'           => $dbName,
            'mongo_username' => $mongoUser,
            'status'         => 'active',
        ]);

        return redirect()->route('user.mongodb.show', $db)
            ->with('success', "MongoDB database \"{$dbName}\" created.")
            ->with('mongo_password', $password); // show once
    }

    public function show(MongoDatabase $mongoDatabase)
    {
        $this->authorizeDb($mongoDatabase);

        $info = [];
        $response = AgentService::for($mongoDatabase->account->server)->post('/mongodb/info', [
            'name' => $mongoDatabase->name,
        ]);
        if ($response && $response->successful()) {
            $info = $response->json();
        }

        return view('mongodb.show', ['db' => $mongoDatabase, 'info' => $info]);
    }

    public function changePassword(Request $request, MongoDatabase $mongoDatabase)
    {
        $this->authorizeDb($mongoDatabase);

        $validated = $request->validate([
            'mongo_password' => 'required|string|min:8',
        ]);

        $response = AgentService::for($mongoDatabase->account->server)->post('/mongodb/user-password', [
            'username' => $mongoDatabase->mongo_username,
            'password' => $validated['mongo_password'],
            'database' => $mongoDatabase->name,
        ]);

        if ($response && $response->successful()) {
            return back()->with('success', 'MongoDB user password updated.');
        }

        $error = $response ? $response->json('error', 'Unknown error') : 'Agent unreachable';
        return back()->with('error', 'Failed to change password: ' . $error);
    }

    public function destroy(Request $request, MongoDatabase $mongoDatabase)
    {
        $this->authorizeDb($mongoDatabase);

        $request->validate(['password' => 'required']);
        if (!auth()->validate(['email' => auth()->user()->email, 'password' => $request->password])) {
            return back()->with('error', 'Incorrect password.');
        }

        // Delete user first, then drop database
        AgentService::for($mongoDatabase->account->server)->post('/mongodb/user-delete', [
            'username' => $mongoDatabase->mongo_username,
            'database' => $mongoDatabase->name,
        ]);

        $response = AgentService::for($mongoDatabase->account->server)->post('/mongodb/delete', [
            'name' => $mongoDatabase->name,
        ]);

        if (!$response || !$response->successful()) {
            $error = $response ? $response->json('error', 'Unknown error') : 'Agent unreachable';
            return back()->with('error', 'Failed to delete MongoDB database: ' . $error);
        }

        $mongoDatabase->delete();

        return redirect()->route('user.mongodb.index')->with('success', "MongoDB database \"{$mongoDatabase->name}\" deleted.");
    }

    private function authorizeDb(MongoDatabase $db): void
    {
        abort_unless(in_array($db->account_id, auth()->user()->currentAccountIds()), 403);
    }
}
