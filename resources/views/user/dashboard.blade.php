<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center space-x-3">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('dashboard.my_dashboard') }}</h2>
                @php
                    $primaryAccount = auth()->user()->currentAccount();
                    $accountIds = $primaryAccount ? [$primaryAccount->id] : [];
                    $myDomains = \App\Models\Domain::with('sslCertificate')->whereIn('account_id', $accountIds)->whereNull('parent_id')->get();
                    $primaryDomain = $myDomains->first();
                @endphp
                @if($primaryDomain)
                    <a href="http://{{ $primaryDomain->domain }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $primaryDomain->domain }}
                        <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $mySubdomains = \App\Models\Domain::whereIn('account_id', $accountIds)->whereNotNull('parent_id')->count();
        $myDatabases = \App\Models\Database::whereIn('account_id', $accountIds)->count();
        $myCerts = \App\Models\SslCertificate::whereHas('domain', fn($q) => $q->whereIn('account_id', $accountIds))->count();
        $myCrons = \App\Models\CronJob::whereIn('account_id', $accountIds)->count();
        $myEmails = \App\Models\EmailAccount::whereHas('domain', fn($q) => $q->whereIn('account_id', $accountIds))->count();

        // Get stats from agent for the current account
        $stats = null;
        if ($primaryAccount) {
            $response = \App\Services\AgentService::for($primaryAccount->server)->post('/stats/account', [
                'username'  => $primaryAccount->username,
                'domains'   => $primaryAccount->domains->pluck('domain')->toArray(),
                'databases' => $primaryAccount->databases->pluck('name')->toArray(),
            ]);
            if ($response && $response->successful()) {
                $stats = $response->json('stats');
            }
        }

        $diskQuota = $primaryAccount?->disk_quota ?? 0;
        $diskUsed = $stats['disk_usage']['total_mb'] ?? 0;
        $diskPercent = $diskQuota > 0 ? min(100, round(($diskUsed / $diskQuota) * 100)) : 0;
        $bandwidthTotal = $stats['bandwidth']['total_mb'] ?? 0;
    @endphp

    <div class="space-y-6" x-data="{ search: '', activeTab: 'all' }">

        {{-- Top Site Command Bar --}}
        @if($primaryDomain)
            <div class="relative overflow-hidden bg-gradient-to-r from-gray-900 via-slate-900 to-gray-900 rounded-2xl p-6 text-white shadow-lg border border-gray-800">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute right-40 -top-10 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center shadow-lg shadow-orange-500/20 shrink-0">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h1 class="text-xl font-bold tracking-tight text-white">{{ $primaryDomain->domain }}</h1>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($primaryDomain->status === 'active') bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                                    @else bg-amber-500/20 text-amber-300 border border-amber-500/30 @endif">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $primaryDomain->status === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' }}"></span>
                                    {{ ucfirst($primaryDomain->status) }}
                                </span>
                                @if($primaryDomain->sslCertificate && $primaryDomain->sslCertificate->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        SSL Secured
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        No SSL
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-800 text-gray-300 border border-gray-700">
                                    PHP {{ $primaryAccount->php_version }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1.5 font-mono truncate max-w-xl">
                                {{ $primaryDomain->document_root }}
                            </p>
                        </div>
                    </div>

                    {{-- Quick Action Buttons in Banner --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('user.filemanager.index') }}"
                           class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-white/10 hover:bg-white/20 rounded-xl backdrop-blur-sm border border-white/10 transition">
                            <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            File Manager
                        </a>
                        <a href="{{ route('user.databases.index') }}"
                           class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-white/10 hover:bg-white/20 rounded-xl backdrop-blur-sm border border-white/10 transition">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                            Databases
                        </a>
                        <a href="{{ route('user.emails.index') }}"
                           class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-white/10 hover:bg-white/20 rounded-xl backdrop-blur-sm border border-white/10 transition">
                            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Email
                        </a>
                        <a href="http://{{ $primaryDomain->domain }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 rounded-xl shadow-md shadow-orange-500/20 transition">
                            Visit Site
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Search & Category Filter Navigation --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="relative w-full sm:w-80">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text"
                       x-model="search"
                       placeholder="Jump to any tool (e.g. SSL, DNS, DB, Git)..."
                       class="w-full pl-10 pr-9 py-2 text-sm bg-gray-50 hover:bg-gray-100/80 focus:bg-white rounded-xl border border-gray-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all outline-none">
                <button type="button" x-show="search.length > 0" @click="search = ''" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0" style="scrollbar-width: none;">
                <button type="button" @click="activeTab = 'all'"
                        :class="activeTab === 'all' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    All
                </button>
                <button type="button" @click="activeTab = 'domain'"
                        :class="activeTab === 'domain' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Domain
                </button>
                <button type="button" @click="activeTab = 'files'"
                        :class="activeTab === 'files' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Files
                </button>
                <button type="button" @click="activeTab = 'databases'"
                        :class="activeTab === 'databases' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Databases
                </button>
                <button type="button" @click="activeTab = 'email'"
                        :class="activeTab === 'email' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Email
                </button>
                <button type="button" @click="activeTab = 'software'"
                        :class="activeTab === 'software' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Software
                </button>
                <button type="button" @click="activeTab = 'advanced'"
                        :class="activeTab === 'advanced' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Advanced
                </button>
                <button type="button" @click="activeTab = 'security'"
                        :class="activeTab === 'security' ? 'bg-orange-600 text-white font-semibold shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium'"
                        class="px-3 py-1.5 rounded-lg text-xs transition shrink-0">
                    Security
                </button>
            </div>
        </div>

        {{-- Main Dashboard Layout: 2 Columns --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Columns: Categorized Interactive Feature Tiles -->
            <div class="lg:col-span-2 space-y-6">

                {{-- Domain Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'domain') && (search === '' || 'domain subdomains dns ssl aliases redirects'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.domain') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-100">5 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        @if($myDomains->isNotEmpty())
                            <a href="{{ route('user.subdomains.index') }}"
                               x-show="search === '' || 'subdomains domain'.includes(search.toLowerCase())"
                               class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-sky-300 hover:shadow-md transition-all">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-sky-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-800 group-hover:text-sky-600 transition">{{ __('dashboard.subdomains') }}</div>
                                    <div class="text-xs text-gray-400 truncate">Manage subdomains</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-sky-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>

                            <a href="{{ route('user.dns.index', $myDomains->first()) }}"
                               x-show="search === '' || 'dns powerdns zone records'.includes(search.toLowerCase())"
                               class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-rose-300 hover:shadow-md transition-all">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-rose-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-800 group-hover:text-rose-600 transition">{{ __('dashboard.dns') }}</div>
                                    <div class="text-xs text-gray-400 truncate">Zone & record manager</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-rose-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endif

                        <a href="{{ route('user.ssl.index') }}"
                           x-show="search === '' || 'ssl tls certificate letsencrypt https'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-emerald-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-emerald-600 transition">{{ __('dashboard.ssl') }}</div>
                                <div class="text-xs text-gray-400 truncate">Let's Encrypt & Custom</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.aliases.index') }}"
                           x-show="search === '' || 'aliases alias domain park'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-amber-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-amber-600 transition">{{ __('dashboard.aliases') }}</div>
                                <div class="text-xs text-gray-400 truncate">Domain aliases & mirrors</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.redirects.index') }}"
                           x-show="search === '' || 'redirects 301 302 forward'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-pink-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-400 to-pink-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-pink-600 transition">{{ __('dashboard.redirects') }}</div>
                                <div class="text-xs text-gray-400 truncate">URL & path forwarding</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-pink-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Files Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'files') && (search === '' || 'files file manager ftp ssh terminal sftp'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.files') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">4 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.filemanager.index') }}"
                           x-show="search === '' || 'file manager files upload edit'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-blue-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-blue-600 transition">{{ __('dashboard.file_manager') }}</div>
                                <div class="text-xs text-gray-400 truncate">Explore, edit & archive</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.ftp.index') }}"
                           x-show="search === '' || 'ftp sftp accounts'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-sky-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-blue-500 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-sky-600 transition">{{ __('dashboard.ftp') }}</div>
                                <div class="text-xs text-gray-400 truncate">ProFTPD user accounts</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-sky-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.ssh.index') }}"
                           x-show="search === '' || 'ssh keys access security'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-gray-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-gray-700 to-gray-900 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-gray-900 transition">{{ __('dashboard.ssh') }}</div>
                                <div class="text-xs text-gray-400 truncate">SSH keys & chroot shell</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-700 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.terminal.index') }}"
                           x-show="search === '' || 'web terminal console bash shell'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-slate-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-800 to-slate-950 text-emerald-400 flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-slate-900 transition">Web Terminal</div>
                                <div class="text-xs text-gray-400 truncate">In-browser root shell</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-slate-700 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Databases Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'databases') && (search === '' || 'databases mysql postgresql phpmyadmin sql'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.databases') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-100">3 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.databases.index') }}"
                           x-show="search === '' || 'mysql mariadb database'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-purple-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-purple-600 transition">MySQL / MariaDB</div>
                                <div class="text-xs text-gray-400 truncate">Databases & users</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-purple-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.postgres.index') }}"
                           x-show="search === '' || 'postgres postgresql database'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-blue-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-sky-700 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.128 0a10.134 10.134 0 00-2.755.38 7.124 7.124 0 00-2.016-.291c-1.176.012-2.306.356-3.231.967a9.933 9.933 0 00-1.685-.218l-.032.005c-1.347.013-4.423 1.396-4.423 6.847a12.418 12.418 0 001.161 5.19l.054.12a16.46 16.46 0 00-.55 4.122c.001 2.826.752 4.21 1.684 4.51.387.124.773.186 1.158.186 1.387 0 2.629-.832 3.388-1.678.44.047.896.07 1.362.07 2.32 0 4.49-.647 6.037-1.874a6.5 6.5 0 002.127-2.706 7.3 7.3 0 001.54-2.16c.744-1.668.744-3.622.744-4.514C21.494 2.695 19.451 0 17.128 0z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-blue-600 transition">PostgreSQL</div>
                                <div class="text-xs text-gray-400 truncate">Postgres instances</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.databases.index') }}"
                           x-show="search === '' || 'phpmyadmin pma sql web'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-amber-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-amber-600 transition">{{ __('dashboard.phpmyadmin') }}</div>
                                <div class="text-xs text-gray-400 truncate">One-click web SSO</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Email Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'email') && (search === '' || 'email mail webmail forwarder autoresponder roundcube'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.email') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 border border-orange-100">4 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.emails.index') }}"
                           x-show="search === '' || 'email accounts mailboxes dove cot'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-amber-500 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">{{ __('dashboard.email_accounts') }}</div>
                                <div class="text-xs text-gray-400 truncate">Mailboxes & quotas</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.forwarders.index') }}"
                           x-show="search === '' || 'forwarders forwarding redirect mail'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-cyan-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-cyan-600 transition">{{ __('dashboard.forwarders') }}</div>
                                <div class="text-xs text-gray-400 truncate">Routing & aliases</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-cyan-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.autoresponders.index') }}"
                           x-show="search === '' || 'autoresponders vacation reply'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-violet-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-400 to-purple-500 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-violet-600 transition">{{ __('dashboard.autoresponders') }}</div>
                                <div class="text-xs text-gray-400 truncate">Vacation / auto-reply</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-violet-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ str_replace('SERVER_IP', $myDomains->first()?->account?->server?->ip_address ?? 'localhost', config('opterius.webmail_url', 'http://SERVER_IP:8090')) }}" target="_blank"
                           x-show="search === '' || 'webmail roundcube inbox client'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-500 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">{{ __('dashboard.webmail') }}</div>
                                <div class="text-xs text-gray-400 truncate">Roundcube webmail</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Software & Developers Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'software') && (search === '' || 'software wordpress laravel joomla drupal magento prestashop node nodejs composer git'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.software') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">9 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.wordpress.index') }}"
                           x-show="search === '' || 'wordpress wp blog cms'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-blue-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-sky-700 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.469 6.825c.84 1.537 1.318 3.3 1.318 5.175 0 3.979-2.156 7.456-5.363 9.325l3.295-9.527c.615-1.539.82-2.771.82-3.864 0-.405-.027-.78-.07-1.109m-7.981.105c.647-.034 1.229-.1 1.229-.1.578-.068.51-.919-.068-.886 0 0-1.739.136-2.86.136-1.052 0-2.825-.136-2.825-.136-.579-.034-.646.852-.068.886 0 0 .549.066 1.13.1l1.68 4.605-2.37 7.08L5.554 6.93c.647-.034 1.229-.1 1.229-.1.578-.068.51-.919-.068-.886 0 0-1.739.136-2.86.136-.201 0-.438-.005-.689-.015C4.911 3.15 8.186 1.213 11.951 1.213c2.8 0 5.35 1.072 7.269 2.818-.046-.003-.091-.009-.141-.009-1.052 0-1.798.919-1.798 1.904 0 .886.51 1.636 1.054 2.522.408.715.886 1.636.886 2.964 0 .919-.354 1.985-.82 3.472l-1.075 3.586-3.894-11.575m-3.007 1.21l-3.357 9.755-2.96-8.115c-.133-.35-.257-.671-.38-.96A8.757 8.757 0 0 1 3.213 12c0-1.665.47-3.222 1.275-4.545m8.463 8.847l2.482-7.19 2.54 6.946c.017.04.036.078.054.114-1.589.666-3.32 1.038-5.138 1.038-.583 0-1.153-.044-1.712-.12"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-blue-600 transition">WordPress</div>
                                <div class="text-xs text-gray-400 truncate">1-click install & security</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.laravel.index') }}"
                           x-show="search === '' || 'laravel php framework'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-red-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M23.642 5.43a.364.364 0 01.014.1v5.149a.361.361 0 01-.181.311l-4.32 2.494v4.934a.36,36 0 01-.181.311l-9.033 5.215a.367.367 0 01-.086.036.369.369 0 01-.274-.036L.548 18.73A.361.361 0 01.364 18.42V2.881a.361.361 0 01.014-.1.357.357 0 01.04-.09.36.36 0 01.056-.063l.01-.01a.36.36 0 01.077-.054L4.93.387a.361.361 0 01.361 0l4.369 2.523a.361.361 0 01.18.311v9.648l3.806-2.198V5.523a.358.358 0 01.015-.1.36.36 0 01.095-.153l.01-.01a.363.363 0 01.077-.054l4.369-2.523a.361.361 0 01.36 0l4.37 2.523a.36.36 0 01.077.054l.01.01a.36.36 0 01.055.063.361.361 0 01.04.09z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-red-600 transition">Laravel</div>
                                <div class="text-xs text-gray-400 truncate">Deploy artisan apps</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-red-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.nodejs.index') }}"
                           x-show="search === '' || 'node nodejs javascript pm2 npm'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-emerald-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.85c-.27 0-.55.07-.78.2L3.78 6.35C3.3 6.6 3 7.1 3 7.65v8.69c0 .56.3 1.06.78 1.31l7.44 4.3c.23.13.5.2.78.2s.55-.07.78-.2l7.44-4.3c.48-.25.78-.75.78-1.31V7.65c0-.55-.3-1.05-.78-1.3l-7.44-4.3c-.23-.13-.5-.2-.78-.2zm0 2.06l6.66 3.85v7.68L12 19.29l-6.66-3.85V7.76L12 3.91z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-emerald-600 transition">Node.js</div>
                                <div class="text-xs text-gray-400 truncate">PM2 process manager</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.git.index') }}"
                           x-show="search === '' || 'git github clone pull deploy'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">Git Version Control</div>
                                <div class="text-xs text-gray-400 truncate">Repositories & auto-deploy</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.composer.index') }}"
                           x-show="search === '' || 'composer php package install'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-amber-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-600 to-yellow-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-amber-600 transition">Composer</div>
                                <div class="text-xs text-gray-400 truncate">Dependencies manager</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cms.index', 'joomla') }}"
                           x-show="search === '' || 'joomla cms'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-amber-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13.196 1.608a5.344 5.344 0 00-3.776.02 3.233 3.233 0 01.496 1.742 3.255 3.255 0 01-3.255 3.254 3.233 3.233 0 01-1.741-.496 5.363 5.363 0 000 7.564l3.96-3.961a1.785 1.785 0 010-2.524 1.781 1.781 0 012.52 0l2.797 2.797 2.796-2.797a1.781 1.781 0 012.52 0 1.785 1.785 0 010 2.524l-3.96 3.96a5.363 5.363 0 007.564 0 5.363 5.363 0 000-7.564l-3.96 3.961a1.785 1.785 0 010-2.524l-2.797-2.797zm-9.784 9.784l-1.804 1.804A5.363 5.363 0 003.172 20.7a5.344 5.344 0 003.776-.021 3.233 3.233 0 01-.496-1.742 3.255 3.255 0 013.255-3.254 3.233 3.233 0 011.741.496 5.363 5.363 0 000-7.564l-3.96 3.96a1.785 1.785 0 010 2.524 1.781 1.781 0 01-2.52 0z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">Joomla</div>
                                <div class="text-xs text-gray-400 truncate">1-click installer</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cms.index', 'drupal') }}"
                           x-show="search === '' || 'drupal cms'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-sky-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M15.78 5.113C14.09 3.425 12.48 1.815 11.998 0c-.48 1.815-2.09 3.424-3.778 5.113-2.534 2.53-5.463 5.46-5.463 9.496a9.241 9.241 0 009.241 9.241 9.241 9.241 0 009.241-9.241c0-4.037-2.928-6.966-5.459-9.496z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-sky-600 transition">Drupal</div>
                                <div class="text-xs text-gray-400 truncate">1-click installer</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-sky-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cms.index', 'magento') }}"
                           x-show="search === '' || 'magento ecommerce store shop'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12.002 0L1.636 6v12l2.545 1.47v-12l7.82-4.515 7.82 4.516v12L22.366 18V6zm1.272 20.06l-1.272.735-1.271-.734V8.029L8.185 9.498v12L12 23.53l3.814-2.03v-12l-2.544-1.47v12.03z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">Magento</div>
                                <div class="text-xs text-gray-400 truncate">E-commerce suite</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cms.index', 'prestashop') }}"
                           x-show="search === '' || 'prestashop ecommerce store shop'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-pink-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.383 0 0 5.383 0 12s5.383 12 12 12 12-5.383 12-12S18.617 0 12 0zm-.005 4.43c2.573 0 4.66 2.087 4.66 4.661 0 2.573-2.087 4.66-4.66 4.66-2.574 0-4.661-2.087-4.661-4.66s2.087-4.66 4.66-4.66zm5.527 14.69H6.478l1.808-4.69h7.427l1.809 4.69z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-pink-600 transition">PrestaShop</div>
                                <div class="text-xs text-gray-400 truncate">Store builder</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-pink-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Advanced Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'advanced') && (search === '' || 'advanced nginx directives php version cron htaccess logs staging'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.advanced') }}</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-50 text-teal-700 border border-teal-100">6 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.nginx-directives.index') }}"
                           x-show="search === '' || 'nginx directives config web server'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-emerald-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-emerald-600 transition">Nginx Directives</div>
                                <div class="text-xs text-gray-400 truncate">Custom location & rules</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.php.index') }}"
                           x-show="search === '' || 'php version fpm extensions'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-indigo-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-indigo-600 transition">{{ __('dashboard.php_version_label') }}</div>
                                <div class="text-xs text-gray-400 truncate">Switch 8.1 - 8.5+</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cronjobs.index') }}"
                           x-show="search === '' || 'cron jobs crontab schedule scheduler'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-teal-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-teal-600 transition">{{ __('dashboard.cron_jobs') }}</div>
                                <div class="text-xs text-gray-400 truncate">Scheduled tasks & runs</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-teal-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.htaccess.index') }}"
                           x-show="search === '' || 'htaccess apache dualstack rewrite'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-amber-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">.htaccess Support</div>
                                <div class="text-xs text-gray-400 truncate">Apache dual-stack mode</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.logs.index') }}"
                           x-show="search === '' || 'logs live stream error access'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-slate-400 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-600 to-gray-800 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-slate-800 transition">Live Logs</div>
                                <div class="text-xs text-gray-400 truncate">Real-time error stream</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-slate-600 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.staging.index') }}"
                           x-show="search === '' || 'staging test environment clone'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-violet-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-violet-600 transition">Staging Environments</div>
                                <div class="text-xs text-gray-400 truncate">1-click test clones</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-violet-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Security & Performance Section --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
                     x-show="(activeTab === 'all' || activeTab === 'security') && (search === '' || 'security cdn performance protection directory hotlink analytics cpanel import'.includes(search.toLowerCase()))">
                    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">Security & Optimization</h3>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-100">5 tools</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <a href="{{ route('user.security.directories.index') }}"
                           x-show="search === '' || 'directory protection password auth htpasswd'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-rose-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-rose-600 transition">Directory Protection</div>
                                <div class="text-xs text-gray-400 truncate">HTTP Basic Auth passwords</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-rose-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.security.hotlink.index') }}"
                           x-show="search === '' || 'hotlink protection leech images bandwidth'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-fuchsia-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-fuchsia-500 to-pink-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-fuchsia-600 transition">Hotlink Protection</div>
                                <div class="text-xs text-gray-400 truncate">Prevent media theft</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-fuchsia-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.cdn.index') }}"
                           x-show="search === '' || 'cdn bunny edge caching speed'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-orange-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-orange-600 transition">BunnyCDN Edge</div>
                                <div class="text-xs text-gray-400 truncate">Global CDN & caching</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.analytics.index') }}"
                           x-show="search === '' || 'analytics traffic visitors logs privacy'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-blue-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-blue-600 transition">Visitor Analytics</div>
                                <div class="text-xs text-gray-400 truncate">Cookie-less web traffic</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('user.migrations.index') }}"
                           x-show="search === '' || 'cpanel import backup migration restore'.includes(search.toLowerCase())"
                           class="group flex items-center gap-3.5 p-3 rounded-xl border border-gray-100 bg-gray-50/60 hover:bg-white hover:border-emerald-300 hover:shadow-md transition-all">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 text-white flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-800 group-hover:text-emerald-600 transition">cPanel Importer</div>
                                <div class="text-xs text-gray-400 truncate">Full migration wizard</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Right Column: Account Insights, Resource Meters & Metrics -->
            <div class="space-y-6">

                {{-- Account & Resource Usage Card --}}
                @if($primaryAccount)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 tracking-wide uppercase">Resource Usage</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Healthy
                            </span>
                        </div>

                        {{-- Disk Usage Gauge --}}
                        <div>
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="font-medium text-gray-700 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                                    {{ __('dashboard.disk_usage') }}
                                </span>
                                <span class="text-xs font-semibold text-gray-800">
                                    {{ number_format($diskUsed, 1) }} MB
                                    <span class="text-gray-400 font-normal">
                                        / @if($diskQuota > 0) {{ $diskQuota >= 1024 ? number_format($diskQuota / 1024, 1) . ' GB' : $diskQuota . ' MB' }} @else Unlimited @endif
                                    </span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden p-0.5">
                                <div class="h-full rounded-full transition-all duration-500 {{ $diskPercent > 85 ? 'bg-gradient-to-r from-red-500 to-rose-600' : ($diskPercent > 65 ? 'bg-gradient-to-r from-amber-400 to-orange-500' : 'bg-gradient-to-r from-orange-500 to-amber-500') }}"
                                     style="width: {{ $diskQuota > 0 ? max(3, $diskPercent) : 5 }}%"></div>
                            </div>
                        </div>

                        {{-- Bandwidth Gauge --}}
                        <div>
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="font-medium text-gray-700 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    {{ __('dashboard.bandwidth') }}
                                </span>
                                <span class="text-xs font-semibold text-gray-800">
                                    {{ $bandwidthTotal >= 1024 ? number_format($bandwidthTotal / 1024, 2) . ' GB' : number_format($bandwidthTotal, 1) . ' MB' }}
                                    <span class="text-gray-400 font-normal">/ Unlimited</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden p-0.5">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: 4%"></div>
                            </div>
                        </div>

                        {{-- Quick Metrics Tile Grid (6 items) --}}
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">{{ __('dashboard.domains') }}</span>
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $myDomains->count() }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">{{ __('dashboard.subdomains') }}</span>
                                    <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $mySubdomains }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">{{ __('dashboard.databases') }}</span>
                                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $myDatabases }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">{{ __('dashboard.email_accounts') }}</span>
                                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $myEmails }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">SSL Certs</span>
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $myCerts }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100 hover:border-gray-200 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500">{{ __('dashboard.cron_jobs') }}</span>
                                    <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="text-xl font-bold text-gray-900 mt-1">{{ $myCrons }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Storage Breakdown Card --}}
                    @if($stats)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                            <h4 class="text-sm font-bold text-gray-900 tracking-wide uppercase">{{ __('dashboard.storage_breakdown') }}</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
                                        <span class="text-sm text-gray-600">{{ __('dashboard.files_label') }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-800">{{ number_format($stats['disk_usage']['home_mb'] ?? 0, 1) }} MB</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                                        <span class="text-sm text-gray-600">{{ __('dashboard.email_label') }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-800">{{ number_format($stats['disk_usage']['email_mb'] ?? 0, 1) }} MB</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2.5 h-2.5 rounded-full bg-purple-500"></div>
                                        <span class="text-sm text-gray-600">{{ __('dashboard.databases_label') }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-800">{{ number_format($stats['database_size_mb'] ?? 0, 1) }} MB</span>
                                </div>
                                <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-600">{{ __('dashboard.inodes') }}</span>
                                    <span class="text-sm font-bold text-gray-900 font-mono">{{ number_format($stats['inode_count'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Package Specifications Card --}}
                    @if($primaryAccount->package)
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <h4 class="text-sm font-bold text-gray-900 tracking-wide uppercase">Hosting Package</h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-200">
                                    {{ $primaryAccount->package->name }}
                                </span>
                            </div>

                            <div class="space-y-2.5 text-xs">
                                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                    <span class="text-gray-500">Default PHP</span>
                                    <span class="font-semibold text-gray-800 bg-gray-100 px-2 py-0.5 rounded">PHP {{ $primaryAccount->php_version }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                    <span class="text-gray-500">{{ __('dashboard.disk_quota') }}</span>
                                    <span class="font-semibold text-gray-800">{{ $primaryAccount->package->diskQuotaLabel() }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                    <span class="text-gray-500">{{ __('dashboard.bandwidth') }}</span>
                                    <span class="font-semibold text-gray-800">{{ $primaryAccount->package->bandwidthLabel() }}</span>
                                </div>
                                @if($primaryAccount->package->max_databases !== null)
                                    <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                        <span class="text-gray-500">{{ __('dashboard.databases') }}</span>
                                        <span class="font-semibold text-gray-800">{{ $primaryAccount->package->limitLabel($primaryAccount->package->max_databases) }}</span>
                                    </div>
                                @endif
                                @if($primaryAccount->package->max_email_accounts !== null)
                                    <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                        <span class="text-gray-500">{{ __('dashboard.email_accounts') }}</span>
                                        <span class="font-semibold text-gray-800">{{ $primaryAccount->package->limitLabel($primaryAccount->package->max_email_accounts) }}</span>
                                    </div>
                                @endif
                                @if($primaryAccount->package->max_subdomains !== null)
                                    <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                        <span class="text-gray-500">{{ __('dashboard.subdomains') }}</span>
                                        <span class="font-semibold text-gray-800">{{ $primaryAccount->package->limitLabel($primaryAccount->package->max_subdomains) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif

            </div>
        </div>

    </div>
</x-user-layout>
