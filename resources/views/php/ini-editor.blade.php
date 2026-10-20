<x-user-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">MultiPHP INI Editor</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                MultiPHP INI Editor
            </h1>
            <p class="text-sm text-gray-500 mt-1">Configure your domain's PHP directives using either the structured <strong>Basic Mode</strong> or the direct <strong>Editor (Advanced Mode)</strong>, just like cPanel.</p>
        </div>

        @if($domains->count() > 1)
            <form method="GET" action="{{ route('user.php.ini-editor') }}" class="flex items-center gap-2 shrink-0">
                <label class="text-xs font-semibold text-slate-600">Select Domain:</label>
                <select name="domain_id" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white shadow-sm focus:outline-none focus:border-indigo-500">
                    @foreach($domains as $d)
                        <option value="{{ $d->id }}" @selected($selectedDomain && $selectedDomain->id === $d->id)>{{ $d->domain }} (PHP {{ $d->php_version }})</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if($selectedDomain)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" x-data="{ mode: 'basic' }">
            {{-- Tabs: Basic Mode vs Advanced Editor --}}
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-4 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Editor Mode:</span>
                    <div class="inline-flex rounded-lg p-1 bg-slate-200">
                        <button type="button" @click="mode = 'basic'"
                                :class="mode === 'basic' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1 text-xs font-semibold rounded-md transition">
                            Basic Mode
                        </button>
                        <button type="button" @click="mode = 'advanced'"
                                :class="mode === 'advanced' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3 py-1 text-xs font-semibold rounded-md transition">
                            Editor (Advanced Mode)
                        </button>
                    </div>
                </div>

                <div class="text-xs text-slate-500 flex items-center gap-2">
                    <span>Target: <strong class="font-mono text-slate-800">{{ $selectedDomain->domain }}</strong></span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-700">PHP {{ $selectedDomain->php_version }}</span>
                </div>
            </div>

            {{-- ── BASIC MODE ── --}}
            <div x-show="mode === 'basic'" class="p-6">
                <form action="{{ route('user.php.config') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="domain_id" value="{{ $selectedDomain->id }}">

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">memory_limit</label>
                            <p class="text-[11px] text-slate-500 mb-2">Maximum amount of memory a script may consume.</p>
                            <input type="text" name="memory_limit" value="{{ $directives['memory_limit'] ?? '256M' }}" placeholder="256M"
                                   class="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">upload_max_filesize</label>
                            <p class="text-[11px] text-slate-500 mb-2">Maximum allowed size for uploaded files.</p>
                            <input type="text" name="upload_max_filesize" value="{{ $directives['upload_max_filesize'] ?? '64M' }}" placeholder="64M"
                                   class="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">post_max_size</label>
                            <p class="text-[11px] text-slate-500 mb-2">Maximum size of POST data accepted by PHP.</p>
                            <input type="text" name="post_max_size" value="{{ $directives['post_max_size'] ?? '64M' }}" placeholder="64M"
                                   class="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">max_execution_time</label>
                            <p class="text-[11px] text-slate-500 mb-2">Max time in seconds a script is allowed to run.</p>
                            <input type="number" name="max_execution_time" value="{{ $directives['max_execution_time'] ?? '30' }}" placeholder="30"
                                   class="w-full text-xs font-mono rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">display_errors</label>
                            <p class="text-[11px] text-slate-500 mb-2">Print out errors on HTML output (Off recommended for live sites).</p>
                            <select name="display_errors" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="Off" @selected(($directives['display_errors'] ?? 'Off') === 'Off')>Off (Production Mode)</option>
                                <option value="On" @selected(($directives['display_errors'] ?? 'Off') === 'On')>On (Debug / Development)</option>
                            </select>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/30">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">allow_url_fopen</label>
                            <p class="text-[11px] text-slate-500 mb-2">Allow PHP file functions to retrieve data from remote URLs.</p>
                            <select name="allow_url_fopen" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="On" @selected(($directives['allow_url_fopen'] ?? 'On') === 'On')>On (Enabled)</option>
                                <option value="Off" @selected(($directives['allow_url_fopen'] ?? 'On') === 'Off')>Off (Disabled)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 border-t border-slate-100">
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                            Apply Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- ── ADVANCED EDITOR MODE ── --}}
            <div x-show="mode === 'advanced'" x-cloak class="p-6">
                <form action="{{ route('user.php.ini-editor.save') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="domain_id" value="{{ $selectedDomain->id }}">

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Direct php.ini Configuration Editor</h3>
                            <p class="text-xs text-slate-500">Write custom PHP directives directly. Directives override server defaults for this domain and reload PHP-FPM instantly.</p>
                        </div>
                        <span class="text-xs font-mono text-slate-400">/etc/php/{{ $selectedDomain->php_version }}/fpm/conf.d/99-domain-{{ $selectedDomain->domain }}.ini</span>
                    </div>

                    <div class="rounded-xl overflow-hidden border border-slate-800 shadow-inner">
                        <textarea name="content" rows="18" spellcheck="false"
                                  class="w-full font-mono text-xs text-emerald-400 bg-slate-900 p-4 leading-relaxed focus:outline-none resize-y border-none"
                                  placeholder="; Add custom php.ini directives here">{{ $rawIni }}</textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <p class="text-xs text-slate-400">Lines beginning with <code class="text-slate-600">;</code> are treated as comments.</p>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                            Save php.ini
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
            No active domains found on this account.
        </div>
    @endif
</x-user-layout>
