<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <a href="{{ route('user.mongodb.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-800">{{ $db->name }}</h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                @if($db->status === 'active') bg-emerald-100 text-emerald-700 @else bg-red-100 text-red-700 @endif">
                {{ ucfirst($db->status) }}
            </span>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    {{-- One-time password display --}}
    @if(session('mongo_password'))
        <div class="mb-6 bg-amber-50 border border-amber-300 rounded-xl p-5">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800 mb-1">Save this password — it will not be shown again</p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="font-mono text-sm bg-amber-100 px-3 py-1.5 rounded-lg text-amber-900 select-all">{{ session('mongo_password') }}</span>
                    </div>
                    <p class="text-xs text-amber-700 mt-2">Use this password with user <strong>{{ $db->mongo_username }}</strong> to connect to your MongoDB database.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Quick Actions --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <a href="{{ route('user.mongodb.export', $db) }}"
           class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export Archive (.archive.gz)
        </a>

        <div x-data="{ open: false }">
            <button type="button" @click="open = true"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import Archive (.archive.gz)
            </button>

            <!-- Import Modal -->
            <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="open" @click="open = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div x-show="open" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900">Import MongoDB Archive into {{ $db->name }}</h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>
                        <form action="{{ route('user.mongodb.import', $db) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-gray-500">Upload a <code>.archive.gz</code> or <code>.gz</code> mongodump archive to restore into this database.</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase mb-2">Select Archive File (max 100MB)</label>
                                <input type="file" name="archive_file" required accept=".gz,.archive" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-lg p-2">
                            </div>
                            <div class="flex justify-end gap-3 pt-3">
                                <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">Cancel</button>
                                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">Upload & Restore</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Database info --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-5">Database Details</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Database Name</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $db->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">User</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $db->mongo_username }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Host</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">127.0.0.1 · port 27017</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Storage Size</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $info['size'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Collections</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $info['collections'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Documents / Objects</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $info['documents'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Hosting Account</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $db->account->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Server</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $db->account->server->name }}</dd>
                </div>
            </dl>

            {{-- Connection Strings --}}
            <div class="mt-6 pt-6 border-t border-gray-100">
                <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Connection Strings</h4>

                <div class="space-y-3">
                    <div>
                        <span class="text-xs text-gray-500 font-medium">Standard URI (Mongoose / Node.js / Python / PHP)</span>
                        <div class="mt-1 p-3 bg-gray-50 rounded-lg font-mono text-xs text-gray-800 select-all overflow-x-auto border border-gray-200">mongodb://{{ $db->mongo_username }}:&lt;password&gt;@127.0.0.1:27017/{{ $db->name }}?authSource={{ $db->name }}</div>
                    </div>

                    <div>
                        <span class="text-xs text-gray-500 font-medium">mongosh / CLI Connection</span>
                        <div class="mt-1 p-3 bg-gray-50 rounded-lg font-mono text-xs text-gray-800 select-all overflow-x-auto border border-gray-200">mongosh "mongodb://127.0.0.1:27017/{{ $db->name }}" -u "{{ $db->mongo_username }}" -p --authenticationDatabase "{{ $db->name }}"</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Change Password & Actions --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Change Password</h3>
                <form action="{{ route('user.mongodb.password', $db) }}" method="POST">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" name="mongo_password" required minlength="8"
                                placeholder="Min 8 characters"
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit"
                            class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

            {{-- Danger Zone --}}
            <div class="bg-white rounded-xl shadow-sm p-6 border border-red-100">
                <h3 class="text-sm font-semibold text-red-600 mb-2">Delete Database</h3>
                <p class="text-xs text-gray-500 mb-4">Permanently drop this MongoDB database and remove user <code class="font-mono text-gray-700">{{ $db->mongo_username }}</code>. All documents and collections will be wiped.</p>
                <form action="{{ route('user.mongodb.destroy', $db) }}" method="POST"
                      onsubmit="let p = prompt('Confirm deletion of {{ $db->name }}.\nEnter your account password:'); if(p) { document.getElementById('del-pwd-show').value = p; return true; } return false;">
                    @csrf
                    <input type="hidden" name="password" id="del-pwd-show" value="">
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                        Delete Database
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-user-layout>
