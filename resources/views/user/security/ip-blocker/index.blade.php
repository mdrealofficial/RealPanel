<x-user-layout>
    <x-slot name="title">IP Blocker (IP Deny Manager)</x-slot>

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
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    IP Blocker (IP Deny Manager)
                </h1>
                <p class="text-slate-500 text-sm mt-1">Block specific IP addresses, scrapers, or CIDR subnets from accessing your websites with web server-level 403 Forbidden enforcement.</p>
            </div>

            @if ($domains->count() > 1)
                <form method="GET" action="{{ route('user.security.ip-blocker.index') }}" class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-600">Domain:</label>
                    <select name="domain_id" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm bg-white shadow-sm focus:outline-none focus:border-red-500">
                        @foreach ($domains as $d)
                            <option value="{{ $d->id }}" @selected($selectedDomain && $selectedDomain->id === $d->id)>{{ $d->domain }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($selectedDomain)
            <!-- Add Blocked IP Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
                <h2 class="text-base font-bold text-slate-900 mb-2">Block an IP Address or Subnet</h2>
                <p class="text-xs text-slate-500 mb-4">You can enter a single IPv4 address (e.g., <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">198.51.100.4</code>) or a CIDR subnet block (e.g., <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">198.51.100.0/24</code>) to ban an entire network.</p>

                <form method="POST" action="{{ route('user.security.ip-blocker.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="domain_id" value="{{ $selectedDomain->id }}">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">IP Address or Range <span class="text-red-500">*</span></label>
                            <input type="text" name="ip_or_subnet" required placeholder="e.g. 203.0.113.15 or 198.51.100.0/24"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-red-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Comment / Reason (Optional)</label>
                            <div class="flex gap-2">
                                <input type="text" name="comment" placeholder="e.g. Aggressive bot scraping /wp-login.php"
                                       class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-5 py-2 rounded-lg transition shadow-sm shrink-0 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Add Block
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Blocked IPs Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-slate-900 text-base">Currently Denied IP Addresses for {{ $selectedDomain->domain }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $blocks->count() }} active deny rule(s) configured in Nginx.</p>
                    </div>
                </div>

                @if ($blocks->isEmpty())
                    <div class="p-12 text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <p class="font-medium text-slate-600 text-sm">No IP addresses are currently blocked for this domain.</p>
                        <p class="text-xs text-slate-400 mt-1">Legitimate traffic from all sources is accepted.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600 text-xs uppercase border-b border-slate-100 font-semibold tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5">IP / CIDR Range</th>
                                    <th class="px-6 py-3.5">Comment</th>
                                    <th class="px-6 py-3.5">Blocked Date</th>
                                    <th class="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-normal text-slate-700">
                                @foreach ($blocks as $block)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-6 py-4 font-mono font-semibold text-slate-900 flex items-center gap-2">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                            {{ $block->ip_or_subnet }}
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 text-xs">
                                            {{ $block->comment ?: '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-400">
                                            {{ $block->created_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="{{ route('user.security.ip-blocker.destroy', $block) }}"
                                                  onsubmit="return confirm('Remove block on {{ $block->ip_or_subnet }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Unblock
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
                You have no domains yet. Add a domain to start managing IP blocking rules.
            </div>
        @endif

    </div>
</x-user-layout>
