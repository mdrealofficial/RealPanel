<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    /**
     * Clear all application caches (config, route, view, cache).
     */
    public function clearCache(Request $request)
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');

            $message = 'Application, view, route, and configuration caches cleared successfully.';
            
            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'ok',
                    'message' => $message,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Cache clear failed: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Force run pending database migrations.
     */
    public function migrate(Request $request)
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            $message = 'Database migrations executed successfully.';
            if (!empty(trim($output))) {
                $message .= ' (' . trim($output) . ')';
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'ok',
                    'message' => $message,
                    'output'  => $output,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Database migration failed: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Database migration failed: ' . $e->getMessage());
        }
    }
}
