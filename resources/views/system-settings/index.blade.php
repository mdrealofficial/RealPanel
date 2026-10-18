<x-admin-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('system-settings.page_title') }}</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <p class="text-sm text-gray-500 mb-6">{{ __('system-settings.page_subtitle') }}</p>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- ── Sidebar: categories ──────────────────────────────────────── --}}
        <aside class="lg:col-span-1">
            <nav class="bg-white rounded-xl shadow-sm overflow-hidden">
                @foreach($categories as $slug => $cat)
                    <a href="{{ route('admin.system-settings.index', ['category' => $slug]) }}"
                       class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-b-0 transition
                              {{ $category === $slug ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $cat['icon'] }}"/>
                        </svg>
                        <span class="text-sm">{{ __('system-settings.cat_' . $slug) }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- ── Right panel: category content ────────────────────────────── --}}
        <main class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-sm p-6 sm:p-8">

                {{-- ── 1. Display & Branding ──────────────────────────────── --}}
                @if($category === 'display')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Display & Branding</h3>
                    <p class="text-sm text-gray-500 mb-6">Customize panel brand name, company title, and portal branding.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'display']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Panel Name</label>
                                <input type="text" name="panel_name" value="{{ $settings['panel_name'] ?? 'RealPanel' }}" required
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Displayed in browser titles, emails, and header.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Company / Organization</label>
                                <input type="text" name="company_name" value="{{ $settings['company_name'] ?? '' }}"
                                       placeholder="e.g. My Hosting Company LLC"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Logo URL (optional)</label>
                                <input type="url" name="logo_url" value="{{ $settings['logo_url'] ?? '' }}"
                                       placeholder="https://example.com/logo.png"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Leave blank to use the default RealPanel logo.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Support Desk / Ticket URL</label>
                                <input type="url" name="support_url" value="{{ $settings['support_url'] ?? '' }}"
                                       placeholder="https://clientarea.example.com/submitticket.php"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Footer Copyright Text</label>
                                <input type="text" name="copyright_text" value="{{ $settings['copyright_text'] ?? '' }}"
                                       placeholder="e.g. © 2026 RealPanel. All rights reserved."
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 2. Domains ────────────────────────────────────────── --}}
                @elseif($category === 'domains')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ __('system-settings.domains_title') }}</h3>
                    <p class="text-sm text-gray-500 mb-6">{{ __('system-settings.domains_subtitle') }}</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'domains']) }}" method="POST">
                        @csrf
                        <div class="mb-6">
                            <label for="default_php_version" class="block text-sm font-medium text-gray-700 mb-1.5">
                                {{ __('system-settings.default_php_version_label') }}
                            </label>
                            <p class="text-xs text-gray-500 mb-2">{{ __('system-settings.default_php_version_hint') }}</p>
                            <select name="default_php_version" id="default_php_version"
                                    class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($extra['php_versions'] as $version)
                                    <option value="{{ $version }}" @selected($extra['default_php_version'] === $version)>
                                        PHP {{ $version }}
                                    </option>
                                @endforeach
                            </select>
                            @error('default_php_version')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 3. Mail ───────────────────────────────────────────── --}}
                @elseif($category === 'mail')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Mail Defaults</h3>
                    <p class="text-sm text-gray-500 mb-6">Server-wide defaults for email accounts and webmail.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'mail']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Default Mailbox Quota (MB)</label>
                                <input type="number" name="default_mailbox_quota" value="{{ $settings['default_mailbox_quota'] ?? '1024' }}" min="100" max="102400" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Default storage allocation for newly created email accounts.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Webmail URL Override (optional)</label>
                                <input type="text" name="webmail_url" value="{{ $settings['webmail_url'] ?? '' }}" placeholder="http://SERVER_IP:8090"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Leave empty to use default server IP port 8090.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Recipients per Outgoing Email</label>
                                <input type="number" name="max_recipients_per_email" value="{{ $settings['max_recipients_per_email'] ?? '50' }}" min="1" max="1000" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="pt-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="enable_dkim_by_default" value="1" @checked(($settings['enable_dkim_by_default'] ?? '1') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Auto-generate DKIM keys for new mail domains</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 4. Security ───────────────────────────────────────── --}}
                @elseif($category === 'security')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Security Policies</h3>
                    <p class="text-sm text-gray-500 mb-6">Authentication rules and brute-force prevention.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'security']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="enforce_2fa" value="1" @checked(($settings['enforce_2fa'] ?? '0') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm font-medium text-gray-800">Enforce Two-Factor Authentication (2FA) for All Administrators</span>
                                </label>
                                <p class="text-xs text-gray-400 ml-6 mt-0.5">Admin users must setup 2FA upon login.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Failed Login Attempts</label>
                                <input type="number" name="max_login_attempts" value="{{ $settings['max_login_attempts'] ?? '5' }}" min="3" max="50" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Number of consecutive failures before IP is temporarily rate-limited.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Session Inactivity Timeout (Minutes)</label>
                                <input type="number" name="session_timeout_minutes" value="{{ $settings['session_timeout_minutes'] ?? '120' }}" min="15" max="1440" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">IP Lockout Duration (Minutes)</label>
                                <input type="number" name="block_duration_minutes" value="{{ $settings['block_duration_minutes'] ?? '15' }}" min="5" max="1440" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 5. SSL ────────────────────────────────────────────── --}}
                @elseif($category === 'ssl')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">SSL & HTTPS</h3>
                    <p class="text-sm text-gray-500 mb-6">Default SSL certificates and encryption parameters.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'ssl']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Default Auto-SSL CA Provider</label>
                                <select name="auto_ssl_provider" class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="letsencrypt" @selected(($settings['auto_ssl_provider'] ?? 'letsencrypt') === 'letsencrypt')>Let's Encrypt (Default)</option>
                                    <option value="zerossl" @selected(($settings['auto_ssl_provider'] ?? '') === 'zerossl')>ZeroSSL</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">ACME Notification Email (optional)</label>
                                <input type="email" name="acme_email" value="{{ $settings['acme_email'] ?? '' }}" placeholder="admin@yourdomain.com"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Used by Let's Encrypt for certificate expiration notices.</p>
                            </div>

                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="force_https_default" value="1" @checked(($settings['force_https_default'] ?? '1') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Enforce HTTP → HTTPS Redirection by Default on New Domains</span>
                                </label>
                            </div>

                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="hsts_enabled" value="1" @checked(($settings['hsts_enabled'] ?? '0') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Enable Strict-Transport-Security (HSTS) Header</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 6. PHP ────────────────────────────────────────────── --}}
                @elseif($category === 'php')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">PHP System Defaults</h3>
                    <p class="text-sm text-gray-500 mb-6">Default baseline runtime constraints for hosted PHP applications.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'php']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Execution Time (seconds)</label>
                                    <input type="number" name="max_execution_time" value="{{ $settings['max_execution_time'] ?? '300' }}" min="10" max="3600" required
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Memory Limit</label>
                                    <input type="text" name="memory_limit" value="{{ $settings['memory_limit'] ?? '256M' }}" required
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Upload Max Filesize</label>
                                    <input type="text" name="upload_max_filesize" value="{{ $settings['upload_max_filesize'] ?? '64M' }}" required
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Post Max Size</label>
                                    <input type="text" name="post_max_size" value="{{ $settings['post_max_size'] ?? '64M' }}" required
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Disabled Functions (comma-separated)</label>
                                <textarea name="disable_functions" rows="3"
                                          class="w-full rounded-lg border-gray-300 shadow-sm text-xs font-mono focus:border-indigo-500 focus:ring-indigo-500">{{ $settings['disable_functions'] ?? 'exec,passthru,shell_exec,system,proc_open,popen' }}</textarea>
                                <p class="text-xs text-gray-400 mt-1">Dangerous PHP functions to restrict across hosting accounts.</p>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 7. Notifications ──────────────────────────────────── --}}
                @elseif($category === 'notifications')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">System Notifications</h3>
                    <p class="text-sm text-gray-500 mb-6">Alert channels and threshold warning configurations.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'notifications']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Admin Notification Email</label>
                                <input type="email" name="alert_email" value="{{ $settings['alert_email'] ?? '' }}" placeholder="sysadmin@example.com"
                                       class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Receives critical server metrics, failure alerts, and SSL warnings.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Disk Space Alert Trigger (%)</label>
                                <input type="number" name="disk_alert_threshold" value="{{ $settings['disk_alert_threshold'] ?? '85' }}" min="50" max="99" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Alert triggered when server or user quota usage exceeds this percentage.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">SSL Expiry Alert Notice (Days)</label>
                                <input type="number" name="ssl_expiry_alert_days" value="{{ $settings['ssl_expiry_alert_days'] ?? '14' }}" min="3" max="60" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="pt-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="notify_on_account_create" value="1" @checked(($settings['notify_on_account_create'] ?? '1') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Notify admin whenever an account is created via WHMCS/API</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 8. Integrations ───────────────────────────────────── --}}
                @elseif($category === 'integrations')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Third-Party Integrations</h3>
                    <p class="text-sm text-gray-500 mb-6">API credentials for external services that the panel uses on behalf of customers.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'integrations']) }}" method="POST">
                        @csrf

                        {{-- BunnyCDN --}}
                        <div class="rounded-xl border border-gray-200 p-5 mb-5">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h4 class="font-bold text-gray-900">BunnyCDN</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Image and asset CDN. Customers can enable per-domain acceleration powered by your BunnyCDN account.</p>
                                </div>
                                @if (! empty($settings['integrations_bunnycdn_api_key'] ?? ($settings['bunnycdn_api_key'] ?? null)))
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 bg-green-100 px-2.5 py-1 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Configured
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
                                        Not configured
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
                                    <input type="password" name="bunnycdn_api_key" autocomplete="off"
                                           value="{{ $settings['integrations_bunnycdn_api_key'] ?? ($settings['bunnycdn_api_key'] ?? '') }}"
                                           placeholder="Paste your BunnyCDN API key"
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Zone Name Prefix</label>
                                    <input type="text" name="bunnycdn_prefix" maxlength="32" pattern="[a-z0-9-]*"
                                           value="{{ $settings['integrations_bunnycdn_prefix'] ?? ($settings['bunnycdn_prefix'] ?? 'realpanel') }}"
                                           class="w-full max-w-sm rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>

                        {{-- MaxMind GeoLite2 --}}
                        <div class="rounded-xl border border-gray-200 p-5 mb-5">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h4 class="font-bold text-gray-900">MaxMind GeoLite2</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Free IP-to-country database powering visitor analytics country flags.</p>
                                </div>
                                @if (! empty($settings['integrations_maxmind_license_key'] ?? ($settings['maxmind_license_key'] ?? null)))
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 bg-green-100 px-2.5 py-1 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Configured
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
                                        Not configured
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Account ID</label>
                                    <input type="text" name="maxmind_account_id" autocomplete="off"
                                           value="{{ $settings['integrations_maxmind_account_id'] ?? ($settings['maxmind_account_id'] ?? '') }}"
                                           placeholder="123456"
                                           class="w-full max-w-sm rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">License Key</label>
                                    <input type="password" name="maxmind_license_key" autocomplete="off"
                                           value="{{ $settings['integrations_maxmind_license_key'] ?? ($settings['maxmind_license_key'] ?? '') }}"
                                           placeholder="Paste your MaxMind license key"
                                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between gap-3">
                                <p class="text-xs text-gray-500">After saving credentials, click Download to install GeoLite2 on every server.</p>
                                <button type="button"
                                        onclick="document.getElementById('maxmind-download-form').submit();"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Download GeoLite2
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                    <form id="maxmind-download-form" action="{{ route('admin.system-settings.maxmind-download') }}" method="POST" class="hidden">
                        @csrf
                    </form>

                {{-- ── 9. System ─────────────────────────────────────────── --}}
                @elseif($category === 'system')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">System Operation</h3>
                    <p class="text-sm text-gray-500 mb-6">Backup lifecycle, timezone, and operational maintenance mode.</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'system']) }}" method="POST">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Backup Retention (Days)</label>
                                <input type="number" name="backup_retention_days" value="{{ $settings['backup_retention_days'] ?? '30' }}" min="1" max="365" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Automatic backups older than this will be pruned to save disk space.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">System Timezone</label>
                                <input type="text" name="timezone" value="{{ $settings['timezone'] ?? config('app.timezone', 'UTC') }}" required
                                       class="w-full max-w-xs rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1">Default timezone used for system schedules and logs (e.g. UTC, America/New_York).</p>
                            </div>

                            <div class="pt-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="maintenance_mode" value="1" @checked(($settings['maintenance_mode'] ?? '0') === '1')
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Enable Maintenance Mode (Restricts panel access to administrators only)</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </main>
    </div>
</x-admin-layout>
