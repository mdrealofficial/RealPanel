<x-user-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">Email Routing</h2>
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
            <form method="GET" action="{{ route('user.emails.routing.index') }}" class="flex items-end gap-4 max-w-lg">
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
            {{-- Routing Configuration --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Email Routing for {{ $selectedDomain->domain }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Configure how incoming and internal mail for this domain is handled by the server.</p>
                </div>

                <form action="{{ route('user.emails.routing.update', $selectedDomain) }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <div class="space-y-4">
                        {{-- Auto --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="routing === 'auto' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'"
                               x-data="{ routing: '{{ $selectedDomain->mail_routing ?? 'local' }}' }">
                            <input type="radio" name="mail_routing" value="auto"
                                @checked(($selectedDomain->mail_routing ?? 'local') === 'auto')
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Automatically Detect Configuration</span>
                                <span class="block text-xs text-gray-500 mt-0.5">The system analyzes the DNS MX records for this domain to decide whether mail should be routed locally or remotely.</span>
                            </div>
                        </label>

                        {{-- Local Mail Exchanger --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="routing === 'local' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'"
                               x-data="{ routing: '{{ $selectedDomain->mail_routing ?? 'local' }}' }">
                            <input type="radio" name="mail_routing" value="local"
                                @checked(($selectedDomain->mail_routing ?? 'local') === 'local')
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Local Mail Exchanger (Default)</span>
                                <span class="block text-xs text-gray-500 mt-0.5">The server will always accept mail for this domain and deliver it to local mailboxes. Recommended when using RealPanel's built-in webmail.</span>
                            </div>
                        </label>

                        {{-- Remote Mail Exchanger --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="routing === 'remote' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'"
                               x-data="{ routing: '{{ $selectedDomain->mail_routing ?? 'local' }}' }">
                            <input type="radio" name="mail_routing" value="remote"
                                @checked(($selectedDomain->mail_routing ?? 'local') === 'remote')
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Remote Mail Exchanger</span>
                                <span class="block text-xs text-gray-500 mt-0.5">The server will not deliver mail locally. Mail from local PHP scripts or website forms will always be dispatched to external MX servers (e.g. <strong>Google Workspace, Microsoft 365, Zoho</strong>).</span>
                            </div>
                        </label>

                        {{-- Backup Mail Exchanger --}}
                        <label class="flex items-start p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition"
                               :class="routing === 'backup' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'"
                               x-data="{ routing: '{{ $selectedDomain->mail_routing ?? 'local' }}' }">
                            <input type="radio" name="mail_routing" value="backup"
                                @checked(($selectedDomain->mail_routing ?? 'local') === 'backup')
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <div class="ml-3">
                                <span class="block text-sm font-semibold text-gray-900">Backup Mail Exchanger</span>
                                <span class="block text-xs text-gray-500 mt-0.5">The server functions as a backup mail exchanger, queueing incoming mail until the primary remote server is available.</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Save Routing
                        </button>
                    </div>
                </form>
            </div>

            {{-- MX Records Inspector --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Detected MX Records for {{ $selectedDomain->domain }}</h4>
                <p class="text-xs text-gray-500 mb-4">Live DNS MX resolution queried from public DNS.</p>

                @if(empty($mxRecords))
                    <div class="p-4 bg-amber-50 rounded-lg text-xs text-amber-800">
                        No external MX records detected for this domain. If using local email, point MX to your domain or mail server hostname.
                    </div>
                @else
                    <div class="divide-y divide-gray-100 border border-gray-100 rounded-lg overflow-hidden">
                        @foreach($mxRecords as $mx)
                            <div class="px-4 py-3 flex items-center justify-between text-xs font-mono">
                                <span class="text-gray-900 font-semibold">{{ $mx['target'] }}</span>
                                <span class="text-gray-500 bg-gray-100 px-2 py-0.5 rounded">Priority: {{ $mx['pri'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-user-layout>
