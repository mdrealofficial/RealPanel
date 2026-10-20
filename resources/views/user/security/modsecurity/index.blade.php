<x-user-layout>
    <x-slot name="title">ModSecurity Web Application Firewall</x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    ModSecurity WAF Manager
                </h1>
                <p class="text-slate-500 text-sm mt-1">Protect your websites against SQL Injection, Cross-Site Scripting (XSS), Local/Remote File Inclusion, and OWASP Top 10 vulnerabilities with granular rule whitelisting.</p>
            </div>

            @if ($domains->count() > 1)
                <form method="GET" action="{{ route('user.security.modsecurity.index') }}" class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-600">Domain:</label>
                    <select name="domain_id" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white shadow-sm focus:outline-none focus:border-indigo-500">
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}" @selected($selectedDomain && $selectedDomain->id === $d->id)>{{ $d->domain }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($selectedDomain)
            <!-- Status Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold text-slate-900">{{ $selectedDomain->domain }}</h2>
                            @if (!empty($modsecInfo['enabled']))
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                    WAF Active & Filtering
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                                    Disabled
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Engine: ModSecurity 3.x / OWASP Core Rule Set (CRS).
                            @if(empty($modsecInfo['installed']))
                                <span class="text-amber-600 font-medium ml-1">(ModSecurity module not yet loaded in server Nginx; rules are pre-configured)</span>
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('user.security.modsecurity.toggle', $selectedDomain) }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ !empty($modsecInfo['enabled']) ? 0 : 1 }}">
                        <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold transition shadow-sm
                            {{ !empty($modsecInfo['enabled']) ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                            {{ !empty($modsecInfo['enabled']) ? 'Disable ModSecurity' : 'Enable ModSecurity' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Whitelisted Rule IDs / Exceptions -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
                <h2 class="text-base font-bold text-slate-900 mb-1">Rule Exceptions (Whitelisted Rules)</h2>
                <p class="text-xs text-slate-500 mb-4">If legitimate actions (such as saving long blog posts or uploading large files) trigger false positives, you can bypass individual ModSecurity Rule IDs (e.g., <code class="bg-slate-100 px-1 py-0.5 rounded">941100</code>) for this domain without turning off the entire firewall.</p>

                <form method="POST" action="{{ route('user.security.modsecurity.whitelist', $selectedDomain) }}" class="flex flex-col sm:flex-row gap-3 mb-6">
                    @csrf
                    <input type="hidden" name="action" value="add">
                    <div class="flex-1">
                        <input type="text" name="rule_id" required placeholder="Enter numeric Rule ID (e.g. 941100, 981176)"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-5 py-2 rounded-lg transition shadow-sm shrink-0 flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Rule Exception
                    </button>
                </form>

                @php $disabledRules = $modsecInfo['disabled_rules'] ?? []; @endphp
                @if (empty($disabledRules))
                    <div class="py-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                        No rule exceptions configured. All standard ModSecurity OWASP rules apply strictly.
                    </div>
                @else
                    <div class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
                        @foreach ($disabledRules as $ruleId)
                            <div class="flex items-center justify-between p-3.5 hover:bg-slate-50/50">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        SecRuleRemoveById {{ $ruleId }}
                                    </span>
                                    <span class="text-xs text-slate-500">Bypassed for {{ $selectedDomain->domain }}</span>
                                </div>
                                <form method="POST" action="{{ route('user.security.modsecurity.whitelist', $selectedDomain) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="rule_id" value="{{ $ruleId }}">
                                    <button type="submit" class="text-xs font-medium text-slate-400 hover:text-red-600 transition">
                                        Re-enable Rule
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Common Rule ID Reference Guide -->
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6">
                <h3 class="text-sm font-bold text-slate-800 mb-2">Common ModSecurity OWASP Rule Reference</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-600">
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <strong class="font-mono text-indigo-600">941100 - 941380</strong>: Cross-Site Scripting (XSS) Filters
                    </div>
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <strong class="font-mono text-indigo-600">942100 - 942510</strong>: SQL Injection (SQLi) Probing Filters
                    </div>
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <strong class="font-mono text-indigo-600">930100 - 930130</strong>: Local File Inclusion (LFI) & Path Traversal
                    </div>
                    <div class="p-3 bg-white rounded-lg border border-slate-200">
                        <strong class="font-mono text-indigo-600">932100 - 932180</strong>: Remote Code Execution (RCE) / Unix Command Injection
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
                You have no domains yet. Add a domain to manage ModSecurity firewall settings.
            </div>
        @endif

    </div>
</x-user-layout>
