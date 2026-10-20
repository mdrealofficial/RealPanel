<x-user-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">Email Disk Usage & Cleaner</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="space-y-6">

        {{-- Domain Selector --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="GET" action="{{ route('user.emails.disk-usage.index') }}" class="flex items-end gap-4 max-w-lg">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Domain</label>
                    <select name="domain_id" onchange="this.form.submit()"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($domains as $d)
                            <option value="{{ $d->id }}" @selected($selectedDomain && $selectedDomain->id === $d->id)>
                                {{ $d->domain }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($selectedDomain)
            @if(empty($mailboxes))
                <div class="bg-white rounded-xl shadow-sm px-6 py-16 text-center">
                    <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <h3 class="mt-4 text-base font-medium text-gray-700">No mailboxes created for {{ $selectedDomain->domain }}</h3>
                    <p class="mt-2 text-sm text-gray-500">Create an email account under Email Accounts to view disk statistics.</p>
                </div>
            @else
                <div class="space-y-6">
                    @foreach($mailboxes as $mb)
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden" x-data="{ cleaning: false, selectedFolder: 'Trash', daysOld: '30' }">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                        @
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-900">{{ $mb['email'] }}</h4>
                                        <span class="text-xs text-gray-500">Total size: <strong>{{ number_format($mb['total_mb'], 2) }} MB</strong></span>
                                    </div>
                                </div>
                                <button type="button" @click="cleaning = !cleaning"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition">
                                    Clean Messages
                                </button>
                            </div>

                            {{-- Folder Breakdown --}}
                            <div class="p-6">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                                    @foreach($mb['folders'] as $folder)
                                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-center">
                                            <span class="text-xs text-gray-500 uppercase tracking-wide font-medium block">{{ $folder['name'] }}</span>
                                            <span class="text-sm font-semibold text-gray-800 mt-1 block">{{ number_format($folder['size_mb'], 2) }} MB</span>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Cleaning Form Modal/Dropdown --}}
                                <div x-show="cleaning" x-collapse class="mt-5 pt-5 border-t border-gray-100 bg-gray-50 -mx-6 -mb-6 p-6">
                                    <h5 class="text-xs font-semibold text-gray-800 uppercase tracking-wide mb-3">Clean Up Mailbox: {{ $mb['email'] }}</h5>
                                    <form action="{{ route('user.emails.disk-usage.clean') }}" method="POST" class="flex flex-wrap items-end gap-3"
                                          onsubmit="return confirm('Are you sure you want to permanently delete these messages?')">
                                        @csrf
                                        <input type="hidden" name="email" value="{{ $mb['email'] }}">

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Target Folder</label>
                                            <select name="folder" x-model="selectedFolder" class="rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="Trash">Trash</option>
                                                <option value="Junk">Junk / Spam</option>
                                                <option value="Sent">Sent</option>
                                                <option value="Archive">Archive</option>
                                                <option value="INBOX">Inbox</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Messages Older Than</label>
                                            <select name="days_old" x-model="daysOld" class="rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="30">30 days</option>
                                                <option value="60">60 days</option>
                                                <option value="90">90 days</option>
                                                <option value="180">180 days (6 months)</option>
                                                <option value="365">365 days (1 year)</option>
                                            </select>
                                        </div>

                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                            Purge Messages
                                        </button>
                                        <button type="button" @click="cleaning = false"
                                            class="inline-flex items-center px-3 py-2 text-xs text-gray-600 hover:text-gray-900 transition">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>
</x-user-layout>
