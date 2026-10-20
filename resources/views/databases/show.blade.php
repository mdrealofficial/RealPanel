<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <a href="{{ route('user.databases.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-800">{{ $database->name }}</h2>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                @if($database->status === 'active') bg-green-100 text-green-700 @else bg-red-100 text-red-700 @endif">
                {{ ucfirst($database->status) }}
            </span>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-3 mb-6">
        @if(config('realpanel.phpmyadmin_sso_secret') && $database->encrypted_password)
            <a href="{{ route('user.databases.sso', $database) }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center px-4 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                {{ __('databases.open_phpmyadmin') }}
            </a>
        @else
            @php
                $pmaBase = str_replace('SERVER_IP', $database->account->server->ip_address, config('realpanel.phpmyadmin_url', 'https://SERVER_IP:8081'));
            @endphp
            <a href="{{ $pmaBase . '/?server=2' }}"
               target="_blank"
               class="inline-flex items-center px-4 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                {{ __('databases.open_phpmyadmin') }}
            </a>
            @if(config('realpanel.phpmyadmin_sso_secret'))
                <p class="text-xs text-amber-600 mt-1">{{ __('databases.pma_sso_set_password') }}</p>
            @endif
        @endif

        <form action="{{ route('user.databases.repair', $database) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="repair">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {{ __('databases.repair') }}
            </button>
        </form>

        <form action="{{ route('user.databases.repair', $database) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="optimize">
            <button type="submit" class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                {{ __('databases.optimize') }}
            </button>
        </form>

        {{-- Single-Click Export (.sql.gz) --}}
        <a href="{{ route('user.databases.export', $database) }}"
           class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export (.sql.gz)
        </a>

        {{-- Single-Click Import (.sql / .sql.gz) Modal Trigger --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = true"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import (.sql / .gz)
            </button>

            <!-- Import Modal -->
            <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="open" @click="open = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="open" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900">Import SQL into {{ $database->name }}</h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>
                        <form action="{{ route('user.databases.import', $database) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <p class="text-xs text-gray-500">Upload a <code>.sql</code> or <code>.sql.gz</code> file to restore tables directly into this database.</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase mb-2">Select SQL Dump File (max 100MB)</label>
                                <input type="file" name="sql_file" required accept=".sql,.gz" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-lg p-2">
                            </div>
                            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg p-3">
                                <strong>Warning:</strong> Existing tables with matching names may be overwritten.
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

        {{-- Granular Permissions Matrix Modal Trigger --}}
        <div x-data="{ open: false, selectAll: false, perms: ['SELECT','INSERT','UPDATE','DELETE','CREATE','DROP','ALTER','INDEX','CREATE TEMPORARY TABLES','LOCK TABLES','EXECUTE','CREATE VIEW','SHOW VIEW','CREATE ROUTINE','ALTER ROUTINE','EVENT','TRIGGER'] }">
            <button type="button" @click="open = true"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-slate-700 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Manage User Privileges
            </button>

            <!-- Privileges Modal -->
            <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="open" @click="open = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="open" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">User Privileges</h3>
                                <p class="text-xs text-gray-500">Configure privileges for <span class="font-mono font-semibold">{{ $database->db_username }}</span> on <span class="font-mono font-semibold">{{ $database->name }}</span></p>
                            </div>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>
                        <form action="{{ route('user.databases.permissions', $database) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 cursor-pointer">
                                    <input type="checkbox" x-model="selectAll"
                                           @change="document.querySelectorAll('.priv-cb').forEach(c => c.checked = selectAll)"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>ALL PRIVILEGES</span>
                                </label>
                                <span class="text-xs text-gray-400">cPanel-style granular control</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-60 overflow-y-auto py-2">
                                <template x-for="p in perms" :key="p">
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 bg-gray-50 border border-gray-100 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-100">
                                        <input type="checkbox" name="privileges[]" :value="p" checked class="priv-cb rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span x-text="p"></span>
                                    </label>
                                </template>
                            </div>
                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">Cancel</button>
                                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">Save Privileges</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Database Info -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-5">{{ __('databases.database_details') }}</h3>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('databases.database_name') }}</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $database->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('databases.db_username') }}</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $database->db_username }}</dd>
                </div>
                <div x-data="{ revealed: false }">
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">Database Password</dt>
                    @if($database->encrypted_password)
                        <dd class="mt-1 text-sm flex items-center gap-2">
                            <code class="font-mono text-gray-800 bg-gray-50 border border-gray-200 px-2 py-1 rounded select-all"
                                  x-text="revealed ? '{{ $database->encrypted_password }}' : '••••••••••••'"></code>
                            <button type="button" @click="revealed = !revealed"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                <span x-text="revealed ? 'Hide' : 'Reveal'"></span>
                            </button>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $database->encrypted_password }}')"
                                    title="Copy to clipboard"
                                    class="text-xs font-semibold text-gray-500 hover:text-gray-700">
                                Copy
                            </button>
                        </dd>
                    @else
                        <dd class="mt-1 text-sm text-gray-400 italic">
                            Not stored — use the form below to set a new password.
                        </dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('servers.server') }}</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $database->account->server->name }} ({{ $database->account->server->ip_address }})</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('backups.size') }}</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $info['size_mb'] ?? '--' }} MB</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('databases.tables') }}</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $info['table_count'] ?? '--' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('databases.account') }}</dt>
                    <dd class="mt-1 text-sm text-gray-800">{{ $database->account->username }}</dd>
                </div>
            </dl>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">{{ __('databases.change_user_password') }}</h3>
            <form action="{{ route('user.databases.password', $database) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('databases.db_user') }}</label>
                    <input type="text" value="{{ $database->db_username }}" disabled
                        class="w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm text-sm font-mono text-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('databases.new_password') }}</label>
                    <x-password-input name="db_password" id="db_password_change" placeholder="Min 8 characters" :min-length="8" :default-length="20" />
                    @error('db_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ __('databases.update_password') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Tables List -->
    @if(!empty($info['tables']))
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">{{ __('databases.tables') }}</h3>
            </div>
            <div class="hidden sm:grid grid-cols-12 px-6 py-2 text-xs font-medium text-gray-400 uppercase tracking-wide border-b border-gray-100 bg-gray-50">
                <div class="col-span-6">{{ __('databases.table_name') }}</div>
                <div class="col-span-3">{{ __('backups.size') }}</div>
                <div class="col-span-3">{{ __('databases.rows') }}</div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($info['tables'] as $table)
                    <div class="grid grid-cols-12 items-center px-6 py-2.5 text-sm">
                        <div class="col-span-6 font-mono text-gray-800">{{ $table['name'] }}</div>
                        <div class="col-span-3 text-gray-500">{{ $table['size_kb'] }} KB</div>
                        <div class="col-span-3 text-gray-500">{{ $table['rows'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Database Users -->
    @if(!empty($info['users']))
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">{{ __('databases.authorized_users') }}</h3>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($info['users'] as $user)
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="text-sm">
                            <span class="font-mono font-semibold text-gray-800">{{ $user['username'] }}</span>
                            <span class="text-gray-400">@</span>
                            <span class="font-mono text-gray-500">{{ $user['host'] }}</span>
                        </div>
                        <span class="text-xs text-gray-400">{{ $user['host'] === '%' ? __('databases.remote_access_label') : __('databases.local_only') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Danger Zone -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-red-100">
        <h3 class="text-base font-semibold text-red-600 mb-2">{{ __('common.danger_zone') }}</h3>
        <p class="text-sm text-gray-500 mb-4">{{ __('databases.danger_zone_msg') }}</p>

        <x-delete-modal
            :action="route('user.databases.destroy', $database)"
            :title="__('databases.delete_database')"
            :message="__('databases.delete_database_full_msg', ['name' => $database->name, 'username' => $database->db_username])"
            :confirm-password="true">
            <x-slot name="trigger">
                <button type="button" class="inline-flex items-center px-4 py-2.5 bg-white text-red-600 text-sm font-medium border border-red-300 rounded-lg hover:bg-red-50 transition">
                    {{ __('databases.delete_database') }}
                </button>
            </x-slot>
        </x-delete-modal>
    </div>
</x-user-layout>
