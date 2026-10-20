<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">MongoDB Databases</h2>
            <a href="{{ route('user.mongodb.create') }}"
               class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Create Database
            </a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">All Databases</h3>
                <p class="text-sm text-gray-500 mt-1">MongoDB NoSQL databases for your hosting accounts and web apps.</p>
            </div>
        </div>

        @if($databases->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300 mb-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0c-.3 0-.6.1-.8.3L1.6 8.5c-.4.3-.6.8-.6 1.3v8.4c0 .5.2 1 .6 1.3l9.6 8.2c.4.3 1 .3 1.4 0l9.6-8.2c.4-.3.6-.8.6-1.3V9.8c0-.5-.2-1-.6-1.3L12.8.3c-.2-.2-.5-.3-.8-.3zm0 2.2l8 6.8-8 6.8-8-6.8 8-6.8zm-8 8.5l7 6v6.2l-7-6v-6.2zm16 0v6.2l-7 6v-6.2l7-6z"/>
                </svg>
                <h3 class="text-base font-medium text-gray-700">No MongoDB databases yet</h3>
                <p class="mt-2 text-sm text-gray-500">Create your first MongoDB database to get started.</p>
                <a href="{{ route('user.mongodb.create') }}"
                   class="mt-6 inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Create Database
                </a>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($databases as $db)
                    <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center
                                @if($db->status === 'active') bg-emerald-100 @else bg-red-100 @endif">
                                <svg class="w-5 h-5 @if($db->status === 'active') text-emerald-600 @else text-red-600 @endif"
                                     viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0c-.3 0-.6.1-.8.3L1.6 8.5c-.4.3-.6.8-.6 1.3v8.4c0 .5.2 1 .6 1.3l9.6 8.2c.4.3 1 .3 1.4 0l9.6-8.2c.4-.3.6-.8.6-1.3V9.8c0-.5-.2-1-.6-1.3L12.8.3c-.2-.2-.5-.3-.8-.3zm0 2.2l8 6.8-8 6.8-8-6.8 8-6.8zm-8 8.5l7 6v6.2l-7-6v-6.2zm16 0v6.2l-7 6v-6.2l7-6z"/>
                                </svg>
                            </div>
                            <div>
                                <a href="{{ route('user.mongodb.show', $db) }}"
                                   class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition">{{ $db->name }}</a>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    User: <span class="font-mono">{{ $db->mongo_username }}</span>
                                    &middot; {{ $db->account->server->name }}
                                    &middot; {{ $db->account->username }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('user.mongodb.export', $db) }}"
                               title="Export .archive.gz"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Export
                            </a>
                            <a href="{{ route('user.mongodb.show', $db) }}"
                               class="text-xs font-medium text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition">
                                Manage
                            </a>
                            <form action="{{ route('user.mongodb.destroy', $db) }}" method="POST"
                                  onsubmit="return confirm('Delete MongoDB database {{ $db->name }}? This will permanently drop all collections and users.')">
                                @csrf
                                <input type="hidden" name="password" id="del-pwd-{{ $db->id }}" value="">
                                <button type="button"
                                        onclick="let p = prompt('Confirm deletion of {{ $db->name }}.\nEnter your account password:'); if(p) { document.getElementById('del-pwd-{{ $db->id }}').value = p; this.closest('form').submit(); }"
                                        class="text-xs text-red-500 hover:text-red-700 px-2 py-1.5 rounded transition">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-user-layout>
