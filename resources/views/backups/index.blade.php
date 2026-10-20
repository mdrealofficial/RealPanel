<x-admin-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('backups.backups') }}</h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <!-- Server Selector -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5">
            <form method="GET" action="{{ route('admin.backups.index') }}" class="flex items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('backups.server_label') }}</label>
                    <select name="server_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($servers as $server)
                            <option value="{{ $server->id }}" @selected($selectedServer && $selectedServer->id === $server->id)>{{ $server->name }} ({{ $server->ip_address }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">{{ __('backups.manage_btn') }}</button>
            </form>
        </div>
    </div>

    @if($selectedServer)
        <!-- Create Backup with Background Progress -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6"
             x-data="backupManager({{ $selectedServer->id }})" x-init="init()">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">{{ __('backups.create_backup') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Backups run automatically in the background without blocking the interface.</p>
                </div>
                <template x-if="jobRunning">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 animate-pulse">
                        <svg class="w-3.5 h-3.5 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Backup In Progress
                    </span>
                </template>
            </div>

            {{-- Live Progress Card (Visible during or immediately after backup creation) --}}
            <div x-show="jobRunning || jobFinished" x-cloak class="px-6 pt-5 pb-2">
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                 :class="{
                                     'bg-indigo-100 text-indigo-600': jobStatus === 'running',
                                     'bg-green-100 text-green-600': jobStatus === 'completed',
                                     'bg-red-100 text-red-600': jobStatus === 'failed'
                                 }">
                                <template x-if="jobStatus === 'running'">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </template>
                                <template x-if="jobStatus === 'completed'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="jobStatus === 'failed'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </template>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-gray-900" x-text="jobStep || 'Processing background backup...'"></div>
                                <div class="text-xs text-gray-500 font-mono" x-text="activeAccount ? 'Account: ' + activeAccount : ''"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xl font-black text-indigo-600" x-text="jobProgress + '%'"></span>
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-indigo-100/70 rounded-full h-2 overflow-hidden shadow-inner mt-2">
                        <div class="h-2 rounded-full transition-all duration-500 ease-out"
                             :class="{
                                 'bg-indigo-600': jobStatus === 'running',
                                 'bg-green-600': jobStatus === 'completed',
                                 'bg-red-600': jobStatus === 'failed'
                             }"
                             :style="'width: ' + jobProgress + '%'">
                        </div>
                    </div>

                    <template x-if="jobError">
                        <div class="mt-3 p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                            <strong>Error:</strong> <span x-text="jobError"></span>
                        </div>
                    </template>

                    <template x-if="jobStatus === 'completed'">
                        <div class="mt-3 flex items-center justify-between text-xs text-green-700 font-medium">
                            <span>Backup archive generated successfully!</span>
                            <button type="button" @click="window.location.reload()" class="underline font-bold hover:text-green-800">
                                Refresh list
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <form @submit.prevent="startBackup()" class="px-6 py-5">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('backups.account_label') }}</label>
                        <select x-model="selectedAccount" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" data-username="{{ $account->username }}">{{ $account->username }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('backups.backup_type') }}</label>
                        <select x-model="selectedType" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="full">{{ __('backups.full_backup') }}</option>
                            <option value="files">{{ __('backups.files_only') }}</option>
                            <option value="databases">{{ __('backups.databases_only') }}</option>
                            <option value="email">{{ __('backups.email_only') }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="jobRunning"
                        class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                        <template x-if="!jobRunning">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                        </template>
                        <template x-if="jobRunning">
                            <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </template>
                        <span x-text="jobRunning ? 'Backing Up in Background...' : '{{ __('backups.create_backup') }}'">{{ __('backups.create_backup') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Backup List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">{{ __('backups.backups') }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ __('backups.stored_at') }}</p>
            </div>

            @if($backups->isEmpty())
                <div class="px-6 py-16 text-center">
                    <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                    <h3 class="mt-4 text-base font-medium text-gray-700">{{ __('backups.no_backups_yet') }}</h3>
                    <p class="mt-2 text-sm text-gray-500">{{ __('backups.no_backups_create_first') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($backups as $backup)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-100 transition">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center
                                    @if($backup->status === 'completed') bg-green-100 @else bg-red-100 @endif">
                                    <svg class="w-5 h-5 @if($backup->status === 'completed') text-green-600 @else text-red-600 @endif"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">{{ $backup->username }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ ucfirst($backup->type) }} &middot; {{ number_format($backup->size_mb, 1) }} MB &middot; {{ $backup->created_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                    @if($backup->status === 'completed') bg-green-100 text-green-700 @else bg-red-100 text-red-700 @endif">
                                    {{ ucfirst($backup->status) }}
                                </span>

                                <a href="{{ route('admin.backups.browse', $backup) }}"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
                                    Browse
                                </a>

                                <a href="{{ route('admin.backups.download', $backup) }}" target="_blank"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                    {{ __('common.download') }}
                                </a>

                                <form action="{{ route('admin.backups.restore', $backup) }}" method="POST"
                                      x-data="{ restoring: false }" @submit="restoring = true">
                                    @csrf
                                    <button type="submit" :disabled="restoring"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-amber-600 bg-amber-50 rounded-lg hover:bg-amber-100 transition disabled:opacity-50"
                                        onclick="return confirm('{{ __('backups.restore_this_backup') }}')">
                                        <span x-text="restoring ? '{{ __('backups.restoring') }}' : '{{ __('common.restore') }}'">{{ __('common.restore') }}</span>
                                    </button>
                                </form>

                                @if($destinations->count() > 0)
                                    <div x-data="{ open: false }" class="relative">
                                        <button type="button" @click="open = !open" title="Offload to Cloud"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                            Sync Cloud
                                        </button>
                                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 p-2 z-30">
                                            <div class="text-xs font-bold text-gray-700 px-2 py-1 border-b border-gray-100">Select Destination</div>
                                            @foreach($destinations as $dest)
                                                <form action="{{ route('admin.backups.sync-cloud', $backup) }}" method="POST" class="mt-1">
                                                    @csrf
                                                    <input type="hidden" name="destination_id" value="{{ $dest->id }}">
                                                    <button type="submit" class="w-full text-left px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50 rounded flex items-center justify-between">
                                                        <span>{{ $dest->name }}</span>
                                                        <span class="text-xs font-mono uppercase text-gray-400">{{ $dest->type }}</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <form action="{{ route('admin.backups.destroy', $backup) }}" method="POST"
                                      onsubmit="return confirm('{{ __('backups.delete_backup_confirm') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-red-600 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Remote Cloud Backup Destinations --}}
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6"
             x-data="{ showAddModal: false, destType: 's3', testLoading: false, testResult: null }">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                        Remote Cloud Backup Destinations (S3 / B2 / SFTP)
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Offload and sync compressed server backups to offsite cloud storage providers for disaster recovery.</p>
                </div>
                <button type="button" @click="showAddModal = true"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Cloud Destination
                </button>
            </div>

            @if($destinations->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                    <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    No remote cloud backup destinations configured yet.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($destinations as $dest)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/30 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase tracking-wider
                                        {{ $dest->type === 's3' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                                           ($dest->type === 'b2' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400') }}">
                                        {{ $dest->type }}
                                    </span>
                                    <form action="{{ route('admin.backups.destinations.destroy', $dest) }}" method="POST"
                                          onsubmit="return confirm('Delete this cloud backup destination?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-500 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </form>
                                </div>
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $dest->name }}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">
                                    @if(in_array($dest->type, ['s3', 'b2']))
                                        Bucket: {{ $dest->credentials['bucket'] ?? 'N/A' }}
                                        @if(!empty($dest->credentials['path']))
                                            / {{ $dest->credentials['path'] }}
                                        @endif
                                    @elseif($dest->type === 'sftp')
                                        Host: {{ $dest->credentials['host'] ?? 'N/A' }}:{{ $dest->credentials['port'] ?? 22 }}
                                    @endif
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600/50 flex items-center justify-between">
                                <span class="text-[11px] text-gray-400">Added {{ $dest->created_at->diffForHumans() }}</span>
                                <form action="{{ route('admin.backups.destinations.test', $dest) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Test Connection
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Modal Add Destination --}}
            <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div @click.away="showAddModal = false" class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 max-w-lg w-full p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Configure Cloud Destination</h3>
                        <button type="button" @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
                    </div>

                    <form action="{{ route('admin.backups.destinations.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="server_id" value="{{ $selectedServer->id }}">

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Destination Name</label>
                            <input type="text" name="name" required placeholder="e.g. Production AWS S3 Backup"
                                   class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Provider Type</label>
                            <select name="type" x-model="destType" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="s3">Amazon S3 / S3-Compatible (MinIO, Wasabi, DigitalOcean Spaces)</option>
                                <option value="b2">Backblaze B2 (S3 Endpoint)</option>
                                <option value="sftp">Remote SFTP Server</option>
                            </select>
                        </div>

                        {{-- S3 / B2 Inputs --}}
                        <div x-show="destType === 's3' || destType === 'b2'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Endpoint (Optional for AWS, Required for MinIO/Wasabi/B2)</label>
                                <input type="text" name="endpoint" placeholder="e.g. s3.us-west-004.backblazeb2.com"
                                       class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Bucket Name</label>
                                    <input type="text" name="bucket" placeholder="my-backups"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Region</label>
                                    <input type="text" name="region" placeholder="us-east-1"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Access Key ID</label>
                                    <input type="text" name="access_key" placeholder="AKIA..."
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Secret Access Key</label>
                                    <input type="password" name="secret_key" placeholder="••••••••"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Destination Subdirectory Path (Optional)</label>
                                <input type="text" name="path" placeholder="e.g. servers/web01"
                                       class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        {{-- SFTP Inputs --}}
                        <div x-show="destType === 'sftp'" class="space-y-3" x-cloak>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Host / IP</label>
                                    <input type="text" name="host" placeholder="backup.myserver.com"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                                    <input type="number" name="port" value="22"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                                    <input type="text" name="username" placeholder="backupuser"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                                    <input type="password" name="password" placeholder="••••••••"
                                           class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Remote Directory Path</label>
                                <input type="text" name="path" placeholder="/home/backupuser/backups"
                                       class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <button type="button" @click="showAddModal = false"
                                    class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition shadow-sm">
                                Save Destination
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <script>
    function backupManager(serverId) {
        return {
            serverId: serverId,
            selectedAccount: '{{ $accounts->first()?->id ?? "" }}',
            selectedType: 'full',
            activeAccount: '',
            jobId: '',
            jobRunning: false,
            jobFinished: false,
            jobStatus: 'idle',
            jobProgress: 0,
            jobStep: '',
            jobError: '',
            pollInterval: null,

            init() {
                // If there are accounts, initialize active account username
                const accSelect = document.querySelector('select[x-model="selectedAccount"]');
                if (accSelect && accSelect.selectedOptions[0]) {
                    this.activeAccount = accSelect.selectedOptions[0].getAttribute('data-username') || '';
                }
            },

            async startBackup() {
                if (this.jobRunning) return;

                const selectElem = document.querySelector('select[x-model="selectedAccount"]');
                const opt = selectElem ? selectElem.selectedOptions[0] : null;
                const username = opt ? (opt.getAttribute('data-username') || opt.text) : '';
                this.activeAccount = username;

                this.jobRunning = true;
                this.jobFinished = false;
                this.jobStatus = 'running';
                this.jobProgress = 5;
                this.jobStep = 'Dispatched backup job in background...';
                this.jobError = '';

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const resp = await fetch('{{ route('admin.backups.create') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            server_id: this.serverId,
                            account_id: this.selectedAccount,
                            type: this.selectedType
                        })
                    });

                    const data = await resp.json();
                    if (data.status === 'started' || data.status === 'ok') {
                        this.jobId = data.job_id || '';
                        this.jobProgress = data.progress || 10;
                        this.jobStep = data.step || 'Running backup job...';
                        this.startPolling();
                    } else {
                        this.jobRunning = false;
                        this.jobStatus = 'failed';
                        this.jobError = data.error || 'Failed to dispatch backup job';
                    }
                } catch (e) {
                    this.startPolling();
                }
            },

            startPolling() {
                if (this.pollInterval) clearInterval(this.pollInterval);
                this.pollInterval = setInterval(() => this.pollProgress(), 1500);
            },

            async pollProgress() {
                try {
                    let url = `{{ route('admin.backups.progress') }}?server_id=${this.serverId}`;
                    if (this.jobId) url += `&job_id=${encodeURIComponent(this.jobId)}`;
                    if (this.activeAccount) url += `&username=${encodeURIComponent(this.activeAccount)}`;

                    const resp = await fetch(url);
                    if (!resp.ok) return;

                    const data = await resp.json();
                    const job = data.job;
                    if (!job) return;

                    if (job.status === 'running') {
                        this.jobRunning = true;
                        this.jobStatus = 'running';
                        this.jobProgress = job.progress || 15;
                        this.jobStep = job.step || 'Working...';
                    } else if (job.status === 'completed') {
                        this.jobRunning = false;
                        this.jobFinished = true;
                        this.jobStatus = 'completed';
                        this.jobProgress = 100;
                        this.jobStep = job.step || 'Backup completed successfully!';
                        if (this.pollInterval) {
                            clearInterval(this.pollInterval);
                            this.pollInterval = null;
                        }
                    } else if (job.status === 'failed') {
                        this.jobRunning = false;
                        this.jobFinished = true;
                        this.jobStatus = 'failed';
                        this.jobError = job.error || 'Backup job reported failure';
                        this.jobStep = job.step || 'Failed';
                        if (this.pollInterval) {
                            clearInterval(this.pollInterval);
                            this.pollInterval = null;
                        }
                    }
                } catch (e) {}
            }
        };
    }
    </script>
</x-admin-layout>
