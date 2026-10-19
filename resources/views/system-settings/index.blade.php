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

                {{-- ── 2. Domains & DNS ───────────────────────────────────── --}}
                @elseif($category === 'domains')
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ __('system-settings.domains_title') }}</h3>
                    <p class="text-sm text-gray-500 mb-6">{{ __('system-settings.domains_subtitle') }}</p>

                    <form action="{{ route('admin.system-settings.update', ['category' => 'domains']) }}" method="POST">
                        @csrf
                        <div class="space-y-6">
                            <div>
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

                            <div class="pt-6 border-t border-gray-100">
                                <h4 class="text-base font-semibold text-gray-800 mb-1">{{ __('system-settings.nameservers_title') }}</h4>
                                <p class="text-xs text-gray-500 mb-4">{{ __('system-settings.nameservers_hint') }}</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
                                    <div>
                                        <label for="default_ns1" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('system-settings.default_ns1_label') }}
                                        </label>
                                        <input type="text" name="default_ns1" id="default_ns1"
                                               value="{{ old('default_ns1', $extra['default_ns1'] ?? '') }}"
                                               placeholder="ns1.yourdomain.com"
                                               class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="text-xs text-gray-400 mt-1">{{ __('system-settings.default_ns1_subtext') }}</p>
                                        @error('default_ns1')
                                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="default_ns2" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('system-settings.default_ns2_label') }}
                                        </label>
                                        <input type="text" name="default_ns2" id="default_ns2"
                                               value="{{ old('default_ns2', $extra['default_ns2'] ?? '') }}"
                                               placeholder="ns2.yourdomain.com"
                                               class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="text-xs text-gray-400 mt-1">{{ __('system-settings.default_ns2_subtext') }}</p>
                                        @error('default_ns2')
                                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-100">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                {{ __('system-settings.save') }}
                            </button>
                        </div>
                    </form>

                {{-- ── 2b. Default Pages & Error Pages ───────────────────── --}}
                @elseif($category === 'pages')
                    <div x-data="{
                        tab: 'unbound',
                        previewOpen: false,
                        previewDevice: 'desktop',
                        previewUrl: 'https://unassigned-domain.com/',
                        previewCurrentTitle: 'Unbound Domain',
                        currentPreviewKey: 'unbound',
                        openPreview(pageKey) {
                            let el = document.getElementById('textarea_' + pageKey);
                            let val = el ? el.value : '';
                            let replaced = val
                                .replaceAll('{domain}', 'example.com')
                                .replaceAll('{panel_name}', 'RealPanel')
                                .replaceAll('{hostname}', window.location.hostname)
                                .replaceAll('{support_url}', '#');
                            this.previewUrl = (pageKey === 'unbound') ? 'https://unassigned-domain.com/'
                                            : (pageKey === '404') ? 'https://example.com/missing-page'
                                            : (pageKey === 'suspended') ? 'https://suspended-site.com/'
                                            : 'https://example.com/';
                            this.previewCurrentTitle = (pageKey === 'unbound') ? 'Unbound Domain'
                                                     : (pageKey === 'welcome') ? 'Connected Domain (Welcome)'
                                                     : (pageKey === '404') ? 'Server 404 Error'
                                                     : 'Stop / Suspended Page';
                            this.currentPreviewKey = pageKey;
                            this.previewOpen = true;
                            this.$nextTick(() => {
                                let iframe = document.getElementById('preview_iframe');
                                if (iframe) {
                                    iframe.srcdoc = replaced;
                                }
                            });
                        },
                        openInNewTab() {
                            let el = document.getElementById('textarea_' + this.currentPreviewKey);
                            let val = el ? el.value : '';
                            let replaced = val
                                .replaceAll('{domain}', 'example.com')
                                .replaceAll('{panel_name}', 'RealPanel')
                                .replaceAll('{hostname}', window.location.hostname)
                                .replaceAll('{support_url}', '#');
                            let win = window.open('', '_blank');
                            if (win) {
                                win.document.open();
                                win.document.write(replaced);
                                win.document.close();
                            }
                        }
                    }">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ __('system-settings.pages_title') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('system-settings.pages_subtitle') }}</p>
                            </div>
                        </div>

                        {{-- Hidden reset forms --}}
                        <form id="reset_form_unbound" action="{{ route('admin.system-settings.pages.reset') }}" method="POST" class="hidden">
                            @csrf
                            <input type="hidden" name="page" value="unbound">
                        </form>
                        <form id="reset_form_welcome" action="{{ route('admin.system-settings.pages.reset') }}" method="POST" class="hidden">
                            @csrf
                            <input type="hidden" name="page" value="welcome">
                        </form>
                        <form id="reset_form_404" action="{{ route('admin.system-settings.pages.reset') }}" method="POST" class="hidden">
                            @csrf
                            <input type="hidden" name="page" value="404">
                        </form>
                        <form id="reset_form_suspended" action="{{ route('admin.system-settings.pages.reset') }}" method="POST" class="hidden">
                            @csrf
                            <input type="hidden" name="page" value="suspended">
                        </form>

                        {{-- Sub-tab navigation --}}
                        <div class="border-b border-gray-200 mb-6">
                            <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto" aria-label="Pages">
                                <button type="button" @click="tab = 'unbound'"
                                        :class="tab === 'unbound' ? 'border-indigo-600 text-indigo-600 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm whitespace-nowrap rounded-t-lg transition">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    {{ __('system-settings.page_tab_unbound') }}
                                </button>
                                <button type="button" @click="tab = 'welcome'"
                                        :class="tab === 'welcome' ? 'border-indigo-600 text-indigo-600 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm whitespace-nowrap rounded-t-lg transition">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    {{ __('system-settings.page_tab_welcome') }}
                                </button>
                                <button type="button" @click="tab = '404'"
                                        :class="tab === '404' ? 'border-indigo-600 text-indigo-600 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm whitespace-nowrap rounded-t-lg transition">
                                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                    {{ __('system-settings.page_tab_404') }}
                                </button>
                                <button type="button" @click="tab = 'suspended'"
                                        :class="tab === 'suspended' ? 'border-indigo-600 text-indigo-600 bg-indigo-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm whitespace-nowrap rounded-t-lg transition">
                                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                    {{ __('system-settings.page_tab_suspended') }}
                                </button>
                            </nav>
                        </div>

                        <form action="{{ route('admin.system-settings.update', ['category' => 'pages']) }}" method="POST">
                            @csrf

                            {{-- 1. Unbound Domain Page --}}
                            <div x-show="tab === 'unbound'" class="space-y-4">
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-semibold text-gray-800">Unbound Domain Page</h4>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-mono">/var/www/default/index.html</span>
                                        </div>
                                        <p class="text-xs text-gray-500">Served when an unassigned domain points to the server IP via DNS.</p>
                                        <div class="flex items-center gap-1.5 mt-2 text-xs text-gray-500">
                                            <span class="font-medium text-gray-700">{{ __('system-settings.available_placeholders') }}:</span>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{hostname}</code>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{panel_name}</code>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="openPreview('unbound')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 shadow-sm transition">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('system-settings.live_preview') }}
                                        </button>
                                        <button type="button" onclick="if(confirm('Reset Unbound Domain Page to default template?')) document.getElementById('reset_form_unbound').submit();"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-gray-500 hover:text-red-600 text-xs font-medium transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            {{ __('system-settings.reset_default') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <textarea name="page_unbound_html" id="textarea_unbound" rows="18" spellcheck="false"
                                              class="w-full font-mono text-xs bg-gray-900 text-gray-100 rounded-xl p-4 border border-gray-700 shadow-inner focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed">{{ old('page_unbound_html', $extra['pages_content']['unbound'] ?? '') }}</textarea>
                                </div>
                            </div>

                            {{-- 2. Connected Domain (Welcome Page) --}}
                            <div x-show="tab === 'welcome'" class="space-y-4">
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-semibold text-gray-800">Connected Domain Welcome Page</h4>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-mono">/var/www/default/welcome.html</span>
                                        </div>
                                        <p class="text-xs text-gray-500">Template copied to new domains' <code>public_html/index.html</code> upon creation.</p>
                                        <div class="flex items-center gap-1.5 mt-2 text-xs text-gray-500">
                                            <span class="font-medium text-gray-700">{{ __('system-settings.available_placeholders') }}:</span>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{domain}</code>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{panel_name}</code>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="openPreview('welcome')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 shadow-sm transition">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('system-settings.live_preview') }}
                                        </button>
                                        <button type="button" onclick="if(confirm('Reset Welcome Page to default template?')) document.getElementById('reset_form_welcome').submit();"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-gray-500 hover:text-red-600 text-xs font-medium transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            {{ __('system-settings.reset_default') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <textarea name="page_welcome_html" id="textarea_welcome" rows="18" spellcheck="false"
                                              class="w-full font-mono text-xs bg-gray-900 text-gray-100 rounded-xl p-4 border border-gray-700 shadow-inner focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed">{{ old('page_welcome_html', $extra['pages_content']['welcome'] ?? '') }}</textarea>
                                </div>
                            </div>

                            {{-- 3. Server 404 Error Page --}}
                            <div x-show="tab === '404'" class="space-y-4">
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-semibold text-gray-800">Server 404 Error Page</h4>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-mono">/var/www/default/404.html</span>
                                        </div>
                                        <p class="text-xs text-gray-500">Displayed when a requested URL or resource is not found.</p>
                                        <div class="flex items-center gap-1.5 mt-2 text-xs text-gray-500">
                                            <span class="font-medium text-gray-700">{{ __('system-settings.available_placeholders') }}:</span>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{panel_name}</code>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="openPreview('404')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 shadow-sm transition">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('system-settings.live_preview') }}
                                        </button>
                                        <button type="button" onclick="if(confirm('Reset 404 Error Page to default template?')) document.getElementById('reset_form_404').submit();"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-gray-500 hover:text-red-600 text-xs font-medium transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            {{ __('system-settings.reset_default') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <textarea name="page_404_html" id="textarea_404" rows="18" spellcheck="false"
                                              class="w-full font-mono text-xs bg-gray-900 text-gray-100 rounded-xl p-4 border border-gray-700 shadow-inner focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed">{{ old('page_404_html', $extra['pages_content']['404'] ?? '') }}</textarea>
                                </div>
                            </div>

                            {{-- 4. Stop / Suspended Account Page --}}
                            <div x-show="tab === 'suspended'" class="space-y-4">
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-semibold text-gray-800">Stop / Suspended Account Page</h4>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-mono">/var/www/default/suspended.html</span>
                                        </div>
                                        <p class="text-xs text-gray-500">Displayed when an account or domain is suspended or stopped.</p>
                                        <div class="flex items-center gap-1.5 mt-2 text-xs text-gray-500">
                                            <span class="font-medium text-gray-700">{{ __('system-settings.available_placeholders') }}:</span>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{domain}</code>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{panel_name}</code>
                                            <code class="px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded text-[11px]">{support_url}</code>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="openPreview('suspended')"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 shadow-sm transition">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('system-settings.live_preview') }}
                                        </button>
                                        <button type="button" onclick="if(confirm('Reset Suspended Page to default template?')) document.getElementById('reset_form_suspended').submit();"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-gray-500 hover:text-red-600 text-xs font-medium transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            {{ __('system-settings.reset_default') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <textarea name="page_suspended_html" id="textarea_suspended" rows="18" spellcheck="false"
                                              class="w-full font-mono text-xs bg-gray-900 text-gray-100 rounded-xl p-4 border border-gray-700 shadow-inner focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed">{{ old('page_suspended_html', $extra['pages_content']['suspended'] ?? '') }}</textarea>
                                </div>
                            </div>

                            <div class="pt-6 mt-6 border-t border-gray-100 flex items-center justify-between">
                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 shadow-sm transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                    {{ __('system-settings.save_and_sync') }}
                                </button>
                                <span class="text-xs text-gray-400">Syncs immediately to Nginx <code>/var/www/default</code> on all connected servers.</span>
                            </div>
                        </form>

                        {{-- Live Preview Modal --}}
                        <div x-show="previewOpen" style="display: none;"
                             class="fixed inset-0 z-50 overflow-hidden bg-black/80 backdrop-blur-md flex items-center justify-center p-2 sm:p-6"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                            <div @click.away="previewOpen = false"
                                 class="bg-gray-900 rounded-2xl shadow-2xl border border-gray-700/80 overflow-hidden flex flex-col"
                                 style="width: 96vw; max-width: 1300px; height: 90vh; max-height: 920px; min-height: 580px;">

                                {{-- Browser Chrome Header --}}
                                <div class="px-4 py-3 bg-gray-800/95 border-b border-gray-700 flex items-center justify-between gap-4 shrink-0">
                                    {{-- Traffic light dots & Title --}}
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="previewOpen = false" class="w-3 h-3 rounded-full bg-red-500 hover:opacity-80 transition cursor-pointer" title="Close"></button>
                                            <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                            <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-300 hidden sm:inline" x-text="previewCurrentTitle"></span>
                                    </div>

                                    {{-- Simulated URL Bar --}}
                                    <div class="flex-1 max-w-xl mx-auto flex items-center bg-gray-950/80 border border-gray-700 rounded-lg px-3 py-1.5 text-xs text-gray-300 font-mono shadow-inner overflow-hidden">
                                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span class="truncate" x-text="previewUrl"></span>
                                    </div>

                                    {{-- Controls: Viewport switch + Fullscreen new tab + Close --}}
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center bg-gray-950 rounded-lg p-0.5 text-xs border border-gray-700">
                                            <button type="button" @click="previewDevice = 'desktop'"
                                                    :class="previewDevice === 'desktop' ? 'bg-indigo-600 text-white font-semibold' : 'text-gray-400 hover:text-gray-200'"
                                                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                <span class="hidden md:inline">Desktop</span>
                                            </button>
                                            <button type="button" @click="previewDevice = 'tablet'"
                                                    :class="previewDevice === 'tablet' ? 'bg-indigo-600 text-white font-semibold' : 'text-gray-400 hover:text-gray-200'"
                                                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                <span class="hidden md:inline">Tablet</span>
                                            </button>
                                            <button type="button" @click="previewDevice = 'mobile'"
                                                    :class="previewDevice === 'mobile' ? 'bg-indigo-600 text-white font-semibold' : 'text-gray-400 hover:text-gray-200'"
                                                    class="px-2.5 py-1 rounded-md transition flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                <span class="hidden md:inline">Mobile</span>
                                            </button>
                                        </div>

                                        <button type="button" @click="openInNewTab()"
                                                class="px-2.5 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white rounded-lg border border-gray-700 text-xs font-medium flex items-center gap-1 transition" title="Open preview in new tab">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            <span class="hidden sm:inline">New Tab</span>
                                        </button>

                                        <button type="button" @click="previewOpen = false" class="text-gray-400 hover:text-white p-1 rounded-lg hover:bg-gray-800 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Main Canvas area --}}
                                <div class="bg-gray-950 p-2 sm:p-4 overflow-auto flex-1 flex items-center justify-center" style="min-height: 0; flex: 1 1 0%;">
                                    <div :style="previewDevice === 'desktop' ? 'width: 100%; height: 100%;' : (previewDevice === 'tablet' ? 'width: 768px; height: 100%; max-width: 100%;' : 'width: 375px; height: 100%; max-width: 100%;')"
                                         class="bg-black rounded-xl overflow-hidden border border-gray-800 shadow-2xl flex flex-col transition-all duration-300"
                                         style="height: 100%; min-height: 100%;">
                                        <iframe id="preview_iframe"
                                                class="w-full h-full border-0 block"
                                                style="width: 100%; height: 100%; flex: 1 1 0%; min-height: 100%; border: none;"
                                                sandbox="allow-same-origin allow-scripts"></iframe>
                                    </div>
                                </div>

                                {{-- Footer Bar --}}
                                <div class="px-5 py-2.5 bg-gray-900 border-t border-gray-800 flex items-center justify-between text-xs text-gray-400 shrink-0">
                                    <span>Live preview using sample data (<code>example.com</code>, <code>RealPanel</code>)</span>
                                    <button type="button" @click="previewOpen = false"
                                            class="px-4 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 font-medium rounded-lg border border-gray-700 transition">
                                        {{ __('system-settings.close_preview') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

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
