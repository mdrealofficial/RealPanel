<?php

namespace App\Services;

use App\Models\Server;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class DefaultPagesService
{
    /**
     * Get the list of all customizable server pages.
     */
    public static function pages(): array
    {
        return [
            'unbound' => [
                'name'        => 'Unbound Domain Page',
                'description' => 'Displayed when a domain points its DNS to the server IP address, but has not yet been added to any hosting account.',
                'filename'    => 'index.html',
                'vhost_dest'  => '/var/www/default/index.html',
                'tokens'      => ['{hostname}', '{panel_name}'],
            ],
            'welcome' => [
                'name'        => 'Connected Domain (Welcome Page)',
                'description' => 'The default index.html created when a new domain is connected to an account, before the owner uploads their site.',
                'filename'    => 'welcome.html',
                'vhost_dest'  => '/var/www/default/welcome.html',
                'tokens'      => ['{domain}', '{panel_name}'],
            ],
            '404' => [
                'name'        => 'Server 404 Error Page',
                'description' => 'Displayed when a requested URL or resource cannot be found on the server.',
                'filename'    => '404.html',
                'vhost_dest'  => '/var/www/default/404.html',
                'tokens'      => ['{panel_name}'],
            ],
            'suspended' => [
                'name'        => 'Stop / Suspended Account Page',
                'description' => 'Displayed when a hosting account or website has been stopped or suspended by the administrator.',
                'filename'    => 'suspended.html',
                'vhost_dest'  => '/var/www/default/suspended.html',
                'tokens'      => ['{domain}', '{panel_name}', '{support_url}'],
            ],
        ];
    }

    /**
     * Get default HTML template for a specific page.
     */
    public static function getDefault(string $page): string
    {
        return match ($page) {
            'unbound'   => self::defaultUnboundHtml(),
            'welcome'   => self::defaultWelcomeHtml(),
            '404'       => self::default404Html(),
            'suspended' => self::defaultSuspendedHtml(),
            default     => '',
        };
    }

    /**
     * Get HTML content for a page (either customized from DB or default).
     */
    public static function get(string $page): string
    {
        $custom = Setting::get("page_{$page}_html");
        if ($custom !== null && trim($custom) !== '') {
            return $custom;
        }
        return self::getDefault($page);
    }

    /**
     * Save HTML content for a page.
     */
    public static function set(string $page, string $html): void
    {
        Setting::set("page_{$page}_html", $html, 'system_pages');
    }

    /**
     * Reset a page to its original default template.
     */
    public static function reset(string $page): void
    {
        Setting::where('key', "page_{$page}_html")->delete();
        \Illuminate\Support\Facades\Cache::forget("setting:page_{$page}_html");
    }

    /**
     * Render a page with token replacements for live preview or display.
     */
    public static function render(string $page, ?string $rawHtml = null, array $extraTokens = []): string
    {
        $html = $rawHtml ?? self::get($page);
        $panelName = Setting::get('panel_name', 'RealPanel');
        $supportUrl = Setting::get('support_url', '/');

        $tokens = array_merge([
            '{domain}'      => 'example.com',
            '{panel_name}'  => $panelName,
            '{hostname}'    => request()->getHost(),
            '{support_url}' => $supportUrl ?: '#',
        ], $extraTokens);

        return str_replace(array_keys($tokens), array_values($tokens), $html);
    }

    /**
     * Synchronize all 4 default pages to all servers managed by the panel.
     */
    public static function syncToServers(?array $customPages = null): array
    {
        $payload = [
            'unbound_html'   => $customPages['unbound']   ?? self::get('unbound'),
            'welcome_html'   => $customPages['welcome']   ?? self::get('welcome'),
            '404_html'       => $customPages['404']       ?? self::get('404'),
            'suspended_html' => $customPages['suspended'] ?? self::get('suspended'),
        ];

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        try {
            $servers = Server::all();
            foreach ($servers as $server) {
                try {
                    $res = AgentService::for($server)->post('/server/pages-sync', $payload);
                    if ($res && $res->successful()) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "{$server->name}: " . ($res ? $res->body() : 'Unreachable');
                    }
                } catch (\Throwable $e) {
                    $results['failed']++;
                    $results['errors'][] = "{$server->name}: {$e->getMessage()}";
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Could not sync server pages: {$e->getMessage()}");
        }

        return $results;
    }

    public static function defaultUnboundHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Pointed • Pending Configuration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0b0f19;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }
        .bg-glow {
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.05) 50%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .container {
            max-width: 680px;
            width: 100%;
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            letter-spacing: 0.02em;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #38bdf8;
            box-shadow: 0 0 10px #38bdf8;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(56, 189, 248, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }
        .icon-wrap {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.2));
            border: 1px solid rgba(139, 92, 246, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #818cf8;
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .lead {
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 32px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 32px;
            text-align: left;
        }
        .status-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 16px;
        }
        .status-step {
            font-size: 11px;
            font-weight: 700;
            color: #818cf8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .status-card h4 {
            font-size: 14px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 4px;
        }
        .status-card p {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.4;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #4f46e5;
            color: #ffffff;
            border: 1px solid #6366f1;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }
        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .footer {
            margin-top: 36px;
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .footer a { color: #94a3b8; text-decoration: none; }
        .footer a:hover { color: #c7d2fe; }
        @media (max-width: 640px) {
            .container { padding: 32px 20px; }
            .status-grid { grid-template-columns: 1fr; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>
    <div class="container">
        <div class="badge">
            <span class="pulse-dot"></span>
            DNS Connected • Server Online
        </div>
        <div class="icon-wrap">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
            </svg>
        </div>
        <h1>Domain Pointed to Server</h1>
        <p class="lead">
            This domain is currently pointing to this server IP address, but has not yet been assigned to an active hosting account in <strong>{panel_name}</strong>.
        </p>

        <div class="status-grid">
            <div class="status-card">
                <div class="status-step">Step 1</div>
                <h4>DNS Resolving</h4>
                <p>A/AAAA DNS records are properly configured and pointing to this server.</p>
            </div>
            <div class="status-card">
                <div class="status-step">Step 2</div>
                <h4>Domain Unbound</h4>
                <p>No hosting account or virtual host has been configured for this domain yet.</p>
            </div>
            <div class="status-card">
                <div class="status-step">Step 3</div>
                <h4>Ready to Add</h4>
                <p>Log in to your control panel and add this domain to activate your website.</p>
            </div>
        </div>

        <div class="actions">
            <a href="https://{hostname}:8443" class="btn btn-primary">
                Open {panel_name}
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="https://dnschecker.org" target="_blank" rel="noopener" class="btn btn-secondary">
                Verify DNS Records
            </a>
        </div>

        <div class="footer">
            Powered by <strong>{panel_name}</strong> • Next-Gen Web Hosting Platform
        </div>
    </div>
</body>
</html>
HTML;
    }

    public static function defaultWelcomeHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {domain}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            position: relative;
        }
        .bg-gradient {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 15%, rgba(16, 185, 129, 0.12) 0%, rgba(99, 102, 241, 0.08) 40%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .container {
            max-width: 720px;
            width: 100%;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        h1 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .domain-name {
            color: #38bdf8;
            -webkit-text-fill-color: #38bdf8;
        }
        .subtitle {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 36px;
            line-height: 1.5;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
            text-align: left;
        }
        .card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            padding: 20px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            border-color: rgba(99, 102, 241, 0.3);
        }
        .card-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            font-size: 18px;
        }
        .icon-blue { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
        .icon-purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .icon-emerald { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .card h3 {
            font-size: 15px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 6px;
        }
        .card p {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.45;
        }
        .tip-box {
            background: rgba(99, 102, 241, 0.08);
            border: 1px dashed rgba(99, 102, 241, 0.35);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 32px;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }
        .tip-box code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            color: #f1f5f9;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #10b981;
            color: #ffffff;
            border: 1px solid #059669;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .footer {
            margin-top: 36px;
            font-size: 12px;
            color: #64748b;
        }
        @media (max-width: 640px) {
            .container { padding: 32px 20px; }
            .cards { grid-template-columns: 1fr; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="container">
        <div class="badge">
            <span class="pulse-dot"></span>
            Hosting Environment Ready
        </div>
        <h1><span class="domain-name">{domain}</span> is successfully hosted!</h1>
        <p class="subtitle">Your web hosting account has been created and configured on <strong>{panel_name}</strong>.</p>

        <div class="cards">
            <div class="card">
                <div class="card-icon icon-blue">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>
                <h3>1. Upload Content</h3>
                <p>Upload your website files into <code>public_html</code> using File Manager or SFTP.</p>
            </div>
            <div class="card">
                <div class="card-icon icon-purple">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3"/></svg>
                </div>
                <h3>2. Database & Mail</h3>
                <p>Create MySQL databases and business mailboxes in your user control panel.</p>
            </div>
            <div class="card">
                <div class="card-icon icon-emerald">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3>3. Free SSL Ready</h3>
                <p>Automatic SSL certificates protect your domain and all subdomains.</p>
            </div>
        </div>

        <div class="tip-box">
            <svg width="20" height="20" fill="none" stroke="#818cf8" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                To replace this landing page, upload your own files to <code>public_html</code> and delete or overwrite <code>index.html</code>.
            </div>
        </div>

        <div class="actions">
            <a href="https://cpanel.{domain}:8443" class="btn btn-primary">
                Control Panel Login
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="http://webmail.{domain}" class="btn btn-secondary">
                Webmail Login
            </a>
        </div>

        <div class="footer">
            Managed via <strong>{panel_name}</strong> • High Performance Cloud Infrastructure
        </div>
    </div>
</body>
</html>
HTML;
    }

    public static function default404Html(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #090d16;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            text-align: center;
        }
        .container {
            max-width: 580px;
            width: 100%;
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 48px 36px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        .error-code {
            font-size: 96px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #f1f5f9;
        }
        p {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #4f46e5;
            color: #ffffff;
            border: 1px solid #6366f1;
        }
        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1>Page Not Found</h1>
        <p>The page or resource you are looking for doesn't exist, has been removed, or is temporarily unavailable.</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">Return Home</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
        <div class="footer">
            {panel_name} Web Server
        </div>
    </div>
</body>
</html>
HTML;
    }

    public static function defaultSuspendedHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Suspended</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0d1117;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            text-align: center;
        }
        .container {
            max-width: 620px;
            width: 100%;
            background: rgba(22, 27, 34, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 20px;
            padding: 48px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
        .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #ef4444;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .stop-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ef4444;
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #ffffff;
        }
        .lead {
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 28px;
        }
        .info-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 28px;
            text-align: left;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
        }
        .info-box h4 {
            font-size: 14px;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 6px;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #ef4444;
            color: #ffffff;
            border: 1px solid #dc2626;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
        }
        .btn-primary:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-wrap">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div class="badge">
            <span class="stop-dot"></span>
            Service Suspended
        </div>
        <h1>This Website is Currently Inactive</h1>
        <p class="lead">
            The hosting service for this website has been temporarily suspended.
        </p>

        <div class="info-box">
            <h4>Website Owner Notice</h4>
            <p>If you are the owner or administrator of this website, please contact your service provider or log in to your billing portal to reactivate your hosting service.</p>
        </div>

        <div class="actions">
            <a href="{support_url}" class="btn btn-primary">
                Contact Support
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="footer">
            {panel_name} Server Management
        </div>
    </div>
</body>
</html>
HTML;
    }
}
