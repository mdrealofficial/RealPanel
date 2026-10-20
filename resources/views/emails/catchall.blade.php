<x-user-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">Default Address (Catch-All)</h2>
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
            <form method="GET" action="{{ route('user.emails.catchall.index') }}" class="flex items-end gap-4 max-w-lg">
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
            <div class="bg-white rounded-xl shadow-sm overflow-hidden"
                 x-data="{ action: '{{ $selectedDomain->catchall_action ?? 'fail' }}', destination: '{{ $selectedDomain->catchall_destination ?? '' }}' }">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Default Address for {{ $selectedDomain->domain }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Configure how unrouted or misspelled incoming emails sent to <code class="font-mono text-gray-700">*@{{ $selectedDomain->domain }}</code> are handled.</p>
                </div>

                <form action="{{ route('user.emails.catchall.update', $selectedDomain) }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <div class="space-y-4">
                        {{-- Fail --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="action === 'fail' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'">
                            <input type="radio" name="catchall_action" value="fail" x-model="action"
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Discard the email and send an error to the sender (Default)</span>
                                <span class="block text-xs text-gray-500 mt-0.5">The sending mail server will immediately receive an SMTP 550 failure code stating "No such recipient here". Recommended to prevent spam flooding.</span>
                            </div>
                        </label>

                        {{-- Forward --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="action === 'forward' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'">
                            <input type="radio" name="catchall_action" value="forward" x-model="action"
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3 flex-1">
                                <span class="block text-sm font-semibold text-gray-900">Forward to an email address</span>
                                <span class="block text-xs text-gray-500 mt-0.5 mb-2">Forward all unrouted emails to an existing mailbox or central catch-all.</span>
                                <div x-show="action === 'forward'" x-collapse>
                                    <input type="email" name="catchall_destination" x-model="destination"
                                        placeholder="admin@{{ $selectedDomain->domain }} or external@gmail.com"
                                        class="w-full max-w-md rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </label>

                        {{-- Discard --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="action === 'discard' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'">
                            <input type="radio" name="catchall_action" value="discard" x-model="action"
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Discard the email (Blackhole)</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Silently discard unrouted messages without returning an error code to the sender.</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Save Default Address
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
</x-user-layout>
