<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    /**
     * The structure of the System Settings page. Each entry maps a category
     * slug → display label key + icon. Adding a new category is just adding
     * a row here and creating the matching `system-settings/{slug}.blade.php`
     * partial. Categories with no settings yet still appear in the sidebar
     * as "Coming soon" so the page already feels complete.
     */
    private array $categories = [
        'display'       => ['icon' => 'm9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'ready' => true],
        'domains'       => ['icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064', 'ready' => true],
        'pages'         => ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'ready' => true],
        'mail'          => ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'ready' => true],
        'security'      => ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'ready' => true],
        'ssl'           => ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'ready' => true],
        'php'           => ['icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'ready' => true],
        'notifications' => ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'ready' => true],
        'integrations'  => ['icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'ready' => true],
        'system'        => ['icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'ready' => true],
    ];

    /**
     * Show the System Settings page for the requested category. Defaults to 'display'.
     */
    public function index(string $category = 'display')
    {
        if (!array_key_exists($category, $this->categories)) {
            abort(404);
        }

        $settings = Setting::getGroup('system_' . $category);

        $extra = [];
        if ($category === 'domains') {
            $extra['php_versions'] = config('realpanel.php_versions', ['8.1', '8.2', '8.3', '8.4']);
            $extra['default_php_version'] = $settings['default_php_version']
                ?? config('realpanel.default_php_version', '8.3');
            $extra['default_ns1'] = array_key_exists('default_ns1', $settings)
                ? $settings['default_ns1']
                : (array_key_exists('ns1', $settings)
                    ? $settings['ns1']
                    : config('realpanel.ns1', 'ns1.realpanel.io'));
            $extra['default_ns2'] = array_key_exists('default_ns2', $settings)
                ? $settings['default_ns2']
                : (array_key_exists('ns2', $settings)
                    ? $settings['ns2']
                    : config('realpanel.ns2', 'ns2.realpanel.io'));
        } elseif ($category === 'pages') {
            $extra['pages_info'] = \App\Services\DefaultPagesService::pages();
            $extra['pages_content'] = [
                'unbound'   => \App\Services\DefaultPagesService::get('unbound'),
                'welcome'   => \App\Services\DefaultPagesService::get('welcome'),
                '404'       => \App\Services\DefaultPagesService::get('404'),
                'suspended' => \App\Services\DefaultPagesService::get('suspended'),
            ];
        }

        return view('system-settings.index', [
            'categories' => $this->categories,
            'category'   => $category,
            'settings'   => $settings,
            'extra'      => $extra,
        ]);
    }

    /**
     * Save the settings for a category.
     */
    public function update(Request $request, string $category)
    {
        if (!array_key_exists($category, $this->categories)) {
            abort(404);
        }

        switch ($category) {
            case 'display':
                $validated = $request->validate([
                    'panel_name'    => 'required|string|max:100',
                    'company_name'  => 'nullable|string|max:100',
                    'logo_url'      => 'nullable|url|max:255',
                    'support_url'   => 'nullable|url|max:255',
                    'copyright_text'=> 'nullable|string|max:200',
                ]);
                foreach ($validated as $key => $val) {
                    Setting::set($key, $val ?? '', 'system_display');
                }
                break;

            case 'domains':
                $validated = $request->validate([
                    'default_php_version' => 'required|in:' . implode(',', config('realpanel.php_versions', ['8.1', '8.2', '8.3', '8.4'])),
                    'default_ns1'         => 'nullable|string|max:255',
                    'default_ns2'         => 'nullable|string|max:255',
                ]);
                Setting::set('default_php_version', $validated['default_php_version'], 'system_domains');

                $ns1 = isset($validated['default_ns1']) ? rtrim(trim($validated['default_ns1']), '.') : '';
                $ns2 = isset($validated['default_ns2']) ? rtrim(trim($validated['default_ns2']), '.') : '';
                Setting::set('default_ns1', $ns1, 'system_domains');
                Setting::set('default_ns2', $ns2, 'system_domains');
                Setting::set('ns1', $ns1, 'system_domains');
                Setting::set('ns2', $ns2, 'system_domains');
                break;

            case 'pages':
                $validated = $request->validate([
                    'page_unbound_html'   => 'required|string',
                    'page_welcome_html'   => 'required|string',
                    'page_404_html'       => 'required|string',
                    'page_suspended_html' => 'required|string',
                ]);
                \App\Services\DefaultPagesService::set('unbound', $validated['page_unbound_html']);
                \App\Services\DefaultPagesService::set('welcome', $validated['page_welcome_html']);
                \App\Services\DefaultPagesService::set('404', $validated['page_404_html']);
                \App\Services\DefaultPagesService::set('suspended', $validated['page_suspended_html']);

                $sync = \App\Services\DefaultPagesService::syncToServers([
                    'unbound'   => $validated['page_unbound_html'],
                    'welcome'   => $validated['page_welcome_html'],
                    '404'       => $validated['page_404_html'],
                    'suspended' => $validated['page_suspended_html'],
                ]);

                $successMsg = __('system-settings.pages_saved');
                if ($sync['failed'] > 0) {
                    $successMsg .= " (Synced to {$sync['success']} server(s), {$sync['failed']} unreachable).";
                }
                return redirect()->route('admin.system-settings.index', ['category' => 'pages'])
                    ->with('success', $successMsg);

            case 'mail':
                $validated = $request->validate([
                    'default_mailbox_quota' => 'required|integer|min:100|max:102400',
                    'webmail_url'           => 'nullable|string|max:255',
                    'max_recipients_per_email' => 'required|integer|min:1|max:1000',
                    'enable_dkim_by_default'=> 'nullable|boolean',
                ]);
                Setting::set('default_mailbox_quota', (string)$validated['default_mailbox_quota'], 'system_mail');
                Setting::set('webmail_url', $validated['webmail_url'] ?? '', 'system_mail');
                Setting::set('max_recipients_per_email', (string)$validated['max_recipients_per_email'], 'system_mail');
                Setting::set('enable_dkim_by_default', $request->has('enable_dkim_by_default') ? '1' : '0', 'system_mail');
                break;

            case 'security':
                $validated = $request->validate([
                    'enforce_2fa'            => 'nullable|boolean',
                    'max_login_attempts'     => 'required|integer|min:3|max:50',
                    'session_timeout_minutes'=> 'required|integer|min:15|max:1440',
                    'block_duration_minutes' => 'required|integer|min:5|max:1440',
                ]);
                Setting::set('enforce_2fa', $request->has('enforce_2fa') ? '1' : '0', 'system_security');
                Setting::set('max_login_attempts', (string)$validated['max_login_attempts'], 'system_security');
                Setting::set('session_timeout_minutes', (string)$validated['session_timeout_minutes'], 'system_security');
                Setting::set('block_duration_minutes', (string)$validated['block_duration_minutes'], 'system_security');
                break;

            case 'ssl':
                $validated = $request->validate([
                    'auto_ssl_provider'  => 'required|in:letsencrypt,zerossl',
                    'force_https_default'=> 'nullable|boolean',
                    'acme_email'         => 'nullable|email|max:255',
                    'hsts_enabled'       => 'nullable|boolean',
                ]);
                Setting::set('auto_ssl_provider', $validated['auto_ssl_provider'], 'system_ssl');
                Setting::set('force_https_default', $request->has('force_https_default') ? '1' : '0', 'system_ssl');
                Setting::set('acme_email', $validated['acme_email'] ?? '', 'system_ssl');
                Setting::set('hsts_enabled', $request->has('hsts_enabled') ? '1' : '0', 'system_ssl');
                break;

            case 'php':
                $validated = $request->validate([
                    'max_execution_time' => 'required|integer|min:10|max:3600',
                    'memory_limit'       => 'required|string|max:10',
                    'upload_max_filesize'=> 'required|string|max:10',
                    'post_max_size'      => 'required|string|max:10',
                    'disable_functions'  => 'nullable|string|max:1000',
                ]);
                foreach ($validated as $key => $val) {
                    Setting::set($key, $val ?? '', 'system_php');
                }
                break;

            case 'notifications':
                $validated = $request->validate([
                    'alert_email'             => 'nullable|email|max:255',
                    'disk_alert_threshold'    => 'required|integer|min:50|max:99',
                    'ssl_expiry_alert_days'   => 'required|integer|min:3|max:60',
                    'notify_on_account_create'=> 'nullable|boolean',
                ]);
                Setting::set('alert_email', $validated['alert_email'] ?? '', 'system_notifications');
                Setting::set('disk_alert_threshold', (string)$validated['disk_alert_threshold'], 'system_notifications');
                Setting::set('ssl_expiry_alert_days', (string)$validated['ssl_expiry_alert_days'], 'system_notifications');
                Setting::set('notify_on_account_create', $request->has('notify_on_account_create') ? '1' : '0', 'system_notifications');
                break;

            case 'integrations':
                $validated = $request->validate([
                    'bunnycdn_api_key'    => 'nullable|string|max:200',
                    'bunnycdn_prefix'     => 'nullable|string|max:32|regex:/^[a-z0-9-]*$/',
                    'maxmind_account_id'  => 'nullable|string|max:32',
                    'maxmind_license_key' => 'nullable|string|max:80',
                ]);
                Setting::set('integrations_bunnycdn_api_key',    $validated['bunnycdn_api_key']    ?? '', 'system_integrations');
                Setting::set('integrations_bunnycdn_prefix',     $validated['bunnycdn_prefix']     ?? 'realpanel', 'system_integrations');
                Setting::set('integrations_maxmind_account_id',  $validated['maxmind_account_id']  ?? '', 'system_integrations');
                Setting::set('integrations_maxmind_license_key', $validated['maxmind_license_key'] ?? '', 'system_integrations');
                break;

            case 'system':
                $validated = $request->validate([
                    'backup_retention_days' => 'required|integer|min:1|max:365',
                    'maintenance_mode'      => 'nullable|boolean',
                    'timezone'              => 'required|string|max:50',
                ]);
                Setting::set('backup_retention_days', (string)$validated['backup_retention_days'], 'system_system');
                Setting::set('maintenance_mode', $request->has('maintenance_mode') ? '1' : '0', 'system_system');
                Setting::set('timezone', $validated['timezone'], 'system_system');
                break;

            default:
                return back();
        }

        ActivityLogger::log('settings.system_updated', null, null, $category,
            "Updated system settings: {$category}", ['category' => $category]);

        return redirect()->route('admin.system-settings.index', ['category' => $category])
            ->with('success', __('system-settings.saved'));
    }

    /**
     * POST /admin/system-settings/maxmind-download
     * Trigger every server's agent to download the GeoLite2 file using the
     * stored MaxMind license key. Used by the "Download" button in the
     * Integrations panel after the admin saves their credentials.
     */
    public function downloadMaxMind()
    {
        $accountId  = (string) Setting::get('integrations_maxmind_account_id', '');
        $licenseKey = (string) Setting::get('integrations_maxmind_license_key', '');

        if ($accountId === '' || $licenseKey === '') {
            return back()->with('error', 'Save your MaxMind Account ID and License Key first, then click Download.');
        }

        $servers   = Server::all();
        $succeeded = 0;
        $failed    = 0;
        $errors    = [];

        foreach ($servers as $server) {
            $response = AgentService::for($server)->postLong('/analytics/geoip-update', [
                'account_id'  => $accountId,
                'license_key' => $licenseKey,
            ]);

            if ($response && $response->successful()) {
                $succeeded++;
            } else {
                $failed++;
                $errors[] = $server->name . ': ' . ($response?->json('error') ?? 'unreachable');
            }
        }

        ActivityLogger::log('settings.maxmind_downloaded', null, null, 'maxmind',
            "Downloaded GeoLite2 to {$succeeded} server(s)", ['succeeded' => $succeeded, 'failed' => $failed]);

        if ($failed === 0) {
            return back()->with('success', "GeoLite2 database downloaded to {$succeeded} server(s).");
        }
        return back()->with('error',
            "Downloaded to {$succeeded} server(s), failed on {$failed}: " . implode('; ', $errors));
    }

    /**
     * POST /admin/system-settings/pages/preview
     * Render a live preview of any default page with token replacement.
     */
    public function previewPage(Request $request)
    {
        $validated = $request->validate([
            'page' => 'required|in:unbound,welcome,404,suspended',
            'html' => 'nullable|string',
        ]);

        $rendered = \App\Services\DefaultPagesService::render(
            $validated['page'],
            $validated['html'] ?? null
        );

        return response($rendered, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * POST /admin/system-settings/pages/reset
     * Reset a default page to its factory template and sync across all servers.
     */
    public function resetPage(Request $request)
    {
        $validated = $request->validate([
            'page' => 'required|in:unbound,welcome,404,suspended',
        ]);

        \App\Services\DefaultPagesService::reset($validated['page']);
        \App\Services\DefaultPagesService::syncToServers();

        ActivityLogger::log('settings.page_reset', null, null, $validated['page'],
            "Reset default server page template for {$validated['page']}");

        return redirect()->route('admin.system-settings.index', ['category' => 'pages'])
            ->with('success', __('system-settings.page_reset_success', ['page' => ucfirst($validated['page'])]));
    }
}
