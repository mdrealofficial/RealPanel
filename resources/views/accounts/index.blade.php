<x-admin-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('accounts.accounts') }}</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-100">
            <div class="flex items-center space-x-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">{{ __('accounts.all_accounts') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ __('accounts.hosting_accounts_with_isolated') }}</p>
                </div>
                <div class="flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-200">
                    <span class="text-sm font-bold text-gray-700">{{ $currentAccounts }}</span>
                    <span class="text-xs text-gray-400">{{ __('accounts.accounts_label') }}</span>
                </div>
            </div>
            <a href="{{ route('admin.accounts.create') }}" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                {{ __('accounts.create_account') }}
            </a>
        </div>

        <!-- Account List -->
        @if($accounts->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <h3 class="mt-4 text-base font-medium text-gray-700">{{ __('accounts.no_accounts_yet') }}</h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('accounts.create_your_first_account') }}</p>
                <a href="{{ route('admin.accounts.create') }}" class="mt-6 inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ __('accounts.create_account') }}
                </a>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($accounts as $account)
                    <a href="{{ route('admin.accounts.show', $account) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-100 transition">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 rounded-lg {{ $account->suspended ? 'bg-red-100' : 'bg-indigo-100' }} flex items-center justify-center">
                                <span class="text-sm font-bold {{ $account->suspended ? 'text-red-600' : 'text-indigo-600' }}">{{ strtoupper(substr($account->username, 0, 2)) }}</span>
                            </div>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-semibold text-gray-800">{{ $account->username }}</span>
                                    @if($account->suspended)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">{{ __('common.suspended') }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $account->domains->first()?->domain ?? __('accounts.no_domain') }}
                                    &middot; {{ $account->server->name }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-6">
                            <div class="hidden md:flex items-center space-x-6 text-sm text-gray-500">
                                <span>{{ $account->domains->count() }} {{ __('domains.domains') }}</span>
                                <span>{{ $account->databases->count() }} {{ __('accounts.databases') }}</span>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-admin-layout>
