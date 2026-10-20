<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Disk Space Usage Analyzer</h2>
                <p class="text-sm text-gray-500 mt-1">Interactive storage inspection and cleanup for account <strong>{{ $account->username }}</strong></p>
            </div>
            <div class="flex items-center gap-2">
                <form action="{{ route('user.disk-usage.clean') }}" method="POST" onsubmit="return confirm('Empty .trash folder? Deleted items will be permanently removed.')">
                    @csrf
                    <input type="hidden" name="action" value="empty_trash">
                    <button type="submit" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Empty Trash
                    </button>
                </form>

                <form action="{{ route('user.disk-usage.clean') }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Clear Archived Logs
                    </button>
                </form>

                <form action="{{ route('user.disk-usage.clean') }}" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="clear_tmp">
                    <button type="submit" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Clear Tmp
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @php
        $totalMB = (float) ($data['total_mb'] ?? 0);
        $quota = (float) $quotaMB;
        $pct = $quota > 0 ? min(100, round(($totalMB / $quota) * 100, 1)) : 0;
        $homeMB = round(($data['home_bytes'] ?? 0) / (1024 * 1024), 2);
        $mailMB = round(($data['mail_bytes'] ?? 0) / (1024 * 1024), 2);
        $dbMB = round(($data['database_bytes'] ?? 0) / (1024 * 1024), 2);
    @endphp

    {{-- Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Used</div>
            <div class="text-2xl font-black text-gray-900 mt-2">{{ number_format($totalMB, 1) }} <span class="text-sm font-normal text-gray-500">MB</span></div>
            <div class="w-full bg-gray-100 rounded-full h-2 mt-3 overflow-hidden">
                <div class="h-2 rounded-full @if($pct > 90) bg-red-500 @elseif($pct > 70) bg-amber-500 @else bg-indigo-600 @endif" style="width: {{ $pct }}%"></div>
            </div>
            <div class="text-xs text-gray-500 mt-2 flex justify-between">
                <span>{{ $pct }}% of quota</span>
                <span>{{ number_format($quota, 0) }} MB limit</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Files & Web</div>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-gray-900 mt-2">{{ number_format($homeMB, 1) }} <span class="text-sm font-normal text-gray-500">MB</span></div>
            <p class="text-xs text-gray-400 mt-1">/home/{{ $account->username }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Email Accounts</div>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-gray-900 mt-2">{{ number_format($mailMB, 1) }} <span class="text-sm font-normal text-gray-500">MB</span></div>
            <p class="text-xs text-gray-400 mt-1">Mailboxes & Attachments</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Databases</div>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-gray-900 mt-2">{{ number_format($dbMB, 1) }} <span class="text-sm font-normal text-gray-500">MB</span></div>
            <p class="text-xs text-gray-400 mt-1">MySQL tables & indexes</p>
        </div>
    </div>

    {{-- Interactive Breakdown Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ search: '', sort: 'size' }">
        <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-900">Directory & Storage Breakdown</h3>
                <p class="text-xs text-gray-400 mt-0.5">Click into directories or open in File Manager</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="text" x-model="search" placeholder="Filter folders..."
                       class="text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-indigo-500 focus:bg-white transition">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider font-semibold border-b border-gray-100">
                        <th class="py-3 px-6">Folder / Resource</th>
                        <th class="py-3 px-6">Category</th>
                        <th class="py-3 px-6">Absolute Path</th>
                        <th class="py-3 px-6 text-right">Size</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($data['breakdown'] ?? [] as $item)
                        <tr class="hover:bg-gray-50 transition" x-show="search === '' || '{{ strtolower($item['name']) }}'.includes(search.toLowerCase())">
                            <td class="py-3.5 px-6 font-semibold text-gray-800 flex items-center gap-2.5">
                                @if($item['category'] === 'web')
                                    <span class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                    </span>
                                @elseif($item['category'] === 'mail')
                                    <span class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </span>
                                @elseif($item['category'] === 'database')
                                    <span class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                    </span>
                                @elseif($item['category'] === 'trash')
                                    <span class="w-7 h-7 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </span>
                                @else
                                    <span class="w-7 h-7 rounded-md bg-gray-100 text-gray-500 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    </span>
                                @endif
                                <span>{{ $item['name'] }}</span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    @if($item['category'] === 'web') bg-indigo-50 text-indigo-700
                                    @elseif($item['category'] === 'mail') bg-emerald-50 text-emerald-700
                                    @elseif($item['category'] === 'database') bg-amber-50 text-amber-700
                                    @elseif($item['category'] === 'trash') bg-rose-50 text-rose-700
                                    @else bg-gray-100 text-gray-600
                                    @endif">
                                    {{ ucfirst($item['category']) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 font-mono text-xs text-gray-500">{{ $item['path'] }}</td>
                            <td class="py-3.5 px-6 text-right font-mono font-bold text-gray-800">
                                @if($item['size_mb'] >= 1024)
                                    {{ number_format($item['size_mb'] / 1024, 2) }} GB
                                @else
                                    {{ number_format($item['size_mb'], 2) }} MB
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                @if($item['category'] === 'web' || $item['category'] === 'other')
                                    <a href="{{ route('user.filemanager.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Browse</a>
                                @elseif($item['category'] === 'mail')
                                    <a href="{{ route('user.emails.disk-usage.index') }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-800">Clean</a>
                                @elseif($item['category'] === 'database')
                                    <a href="{{ route('user.databases.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-800">Manage</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400">No storage data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-user-layout>
