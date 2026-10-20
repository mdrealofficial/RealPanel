<x-admin-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-800">Updates</h2>
    </x-slot>

    <div x-data="updatesManager()" x-init="init()">
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

        @php $mailVersion = $mailVersion ?? null; $mailInstalled = $mailInstalled ?? false; @endphp

    {{-- ── Background Update Progress Card (visible during and right after update) ── --}}
    <div x-show="updating || updateFinished" x-cloak
         class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     :class="{
                         'bg-indigo-100 text-indigo-600': updateStatus === 'running',
                         'bg-green-100 text-green-600': updateStatus === 'completed',
                         'bg-red-100 text-red-600': updateStatus === 'failed'
                     }">
                    <template x-if="updateStatus === 'running'">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </template>
                    <template x-if="updateStatus === 'completed'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="updateStatus === 'failed'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </template>
                </div>
                <div>
                    <h4 class="text-base font-bold text-gray-900" x-text="updateTitle()"></h4>
                    <p class="text-xs text-gray-500 font-mono mt-0.5" x-text="updateStep || 'Processing background update...'"></p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-black text-indigo-600" x-text="updateProgress + '%'"></div>
                <div class="text-[11px] text-gray-400" x-text="updateStatus === 'completed' ? 'Finished' : (updateStatus === 'failed' ? 'Stopped' : 'Processing...')"></div>
            </div>
        </div>

        {{-- Dynamic Progress Bar --}}
        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden shadow-inner mt-2">
            <div class="h-3 rounded-full transition-all duration-500 ease-out"
                 :class="{
                     'bg-gradient-to-r from-indigo-500 to-indigo-600 animate-pulse': updateStatus === 'running',
                     'bg-green-500': updateStatus === 'completed',
                     'bg-red-500': updateStatus === 'failed'
                 }"
                 :style="'width: ' + updateProgress + '%'">
            </div>
        </div>

        <template x-if="updateError">
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                <strong>Error:</strong> <span x-text="updateError"></span>
            </div>
        </template>

        <template x-if="updateStatus === 'completed'">
            <div class="mt-4 flex items-center justify-between bg-green-50 border border-green-200 rounded-lg px-4 py-2.5">
                <span class="text-xs font-medium text-green-800">Panel update completed successfully!</span>
                <button type="button" @click="window.location.reload()"
                        class="text-xs font-semibold px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm transition">
                    Reload Panel
                </button>
            </div>
        </template>
    </div>

    {{-- ── Panel update ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center
                        @if($updateAvailable) bg-amber-100 @else bg-green-100 @endif">
                        @if($updateAvailable)
                            <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        @else
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        @endif
                    </div>
                    <div>
                        <div class="text-lg font-bold text-gray-900">RealPanel v{{ $currentVersion }}</div>
                        <div class="text-sm text-gray-500">
                            @if($updateAvailable)
                                <span class="text-amber-600 font-medium">Version {{ $latestVersion }} is available</span>
                            @elseif($latestVersion)
                                <span class="text-green-600 font-medium">You're up to date</span>
                            @else
                                <span class="text-gray-400">Could not check for updates</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if($updateAvailable)
                        <button type="button" @click="startBackgroundUpdate()" :disabled="updating"
                            class="inline-flex items-center px-5 py-2.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                            <template x-if="!updating">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </template>
                            <template x-if="updating">
                                <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </template>
                            <span x-text="updating ? 'Updating in Background...' : 'Update RealPanel'">Update RealPanel</span>
                        </button>
                    @else
                        <button type="button" @click="startBackgroundUpdate()" :disabled="updating"
                            class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-300 rounded-lg hover:bg-amber-100 transition disabled:opacity-50">
                            <template x-if="!updating">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </template>
                            <template x-if="updating">
                                <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </template>
                            <span x-text="updating ? 'Updating in Background...' : 'Force Update'">Force Update</span>
                        </button>
                        <a href="{{ route('admin.updates.index') }}"
                           class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Check Again
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>


    @if($changelog)
        {{-- ── Changelog ──────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    Current Release Notes (v{{ $currentVersion }})
                </h3>
            </div>
            <div class="px-6 py-5 prose prose-sm max-w-none text-gray-600">
                {!! \Illuminate\Support\Str::markdown($changelog) !!}
            </div>
        </div>
    @endif

    {{-- ── System information ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">System Information</h3>
        </div>
        <div class="px-6 py-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">Panel Version</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $currentVersion }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">Agent Version</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ $agentVersion ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">Webmail Version</dt>
                    <dd class="mt-1 flex items-center gap-3">
                        <span class="text-sm font-mono text-gray-800">{{ $mailVersion ? 'v'.$mailVersion : '—' }}</span>
                        @if($mailInstalled)
                            <form action="{{ route('admin.updates.mail') }}" method="POST"
                                  x-data="{ busy: false }" @submit="busy = true" class="inline">
                                @csrf
                                <button type="submit" :disabled="busy"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs text-gray-500 border border-gray-300 rounded hover:bg-gray-50 transition disabled:opacity-50">
                                    <svg class="w-3 h-3" :class="{ 'animate-spin': busy }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    <span x-text="busy ? 'Updating…' : 'Update'">Update</span>
                                </button>
                            </form>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">Laravel</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ app()->version() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">PHP</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">{{ PHP_VERSION }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-400 uppercase">Server OS</dt>
                    <dd class="mt-1 text-sm font-mono text-gray-800">
                        @php
                            $osRelease = @file_get_contents('/etc/os-release');
                            $prettyName = 'Unknown';
                            if ($osRelease && preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $osRelease, $m)) {
                                $prettyName = $m[1];
                            }
                        @endphp
                        {{ $prettyName }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- ── Update log ───────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Update Log</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400" x-text="lastRefreshed ? 'Last refreshed ' + lastRefreshed : ''"></span>
                <button @click="refreshLog()"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    <svg class="w-3.5 h-3.5 mr-1" :class="{ 'animate-spin': logLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Refresh
                </button>
            </div>
        </div>
        <div class="relative">
            <pre x-ref="logBox"
                 class="text-xs font-mono text-gray-300 bg-gray-900 p-5 overflow-y-auto whitespace-pre-wrap leading-relaxed"
                 style="height: 400px;"
                 x-text="log || 'No log entries yet. The log appears here after the first update check.'"
            ></pre>
        </div>
    </div>

    {{-- ── Advanced (collapsed) — for troubleshooting only ─────────────── --}}
    <div class="mt-6" x-data="{ open: false }">
        <button type="button" @click="open = !open"
            class="inline-flex items-center text-xs font-medium text-gray-500 hover:text-gray-700 transition">
            <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            Advanced
        </button>
        <div x-show="open" x-collapse class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-3">
                These actions are for troubleshooting only. The main <strong>Update RealPanel</strong> button above already updates both the panel and the agent together.
            </p>
            <form action="{{ route('admin.updates.agent') }}" method="POST"
                  x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" :disabled="busy"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition disabled:opacity-50">
                    <svg class="w-3.5 h-3.5 mr-1.5" :class="{ 'animate-spin': busy }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span x-text="busy ? 'Triggering...' : 'Force agent binary re-download'">Force agent binary re-download</span>
                </button>
            </form>
        </div>
        </div>
    </div>

    <script>
    function updatesManager() {
        return {
            updating: false,
            updateFinished: false,
            updateStatus: 'idle', // 'idle', 'running', 'completed', 'failed'
            updateProgress: 0,
            updateStep: '',
            updateError: '',
            pollInterval: null,

            log: @json($updateLog ?? ''),
            logLoading: false,
            lastRefreshed: null,

            init() {
                this.scrollToBottom();
                // Check if an update is already running on page load
                this.checkStatus();
                // Periodic log refresh
                setInterval(() => this.refreshLog(), 10000);
            },

            updateTitle() {
                if (this.updateStatus === 'running') return 'RealPanel Update in Progress';
                if (this.updateStatus === 'completed') return 'Update Completed Successfully';
                if (this.updateStatus === 'failed') return 'Update Encountered an Error';
                return 'RealPanel Update';
            },

            async startBackgroundUpdate() {
                if (this.updating) return;

                if (!confirm('Start RealPanel background update now? This will pull latest updates, apply migrations, and refresh services.')) {
                    return;
                }

                this.updating = true;
                this.updateFinished = false;
                this.updateStatus = 'running';
                this.updateProgress = 10;
                this.updateStep = 'Sending update command to server...';
                this.updateError = '';

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const resp = await fetch('{{ route('admin.updates.run') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({})
                    });

                    const data = await resp.json();
                    if (data.status === 'started' || data.status === 'running') {
                        this.updateProgress = data.progress || 15;
                        this.updateStep = data.step || 'Update running in background...';
                        this.startPolling();
                    } else if (data.status === 'error') {
                        this.updating = false;
                        this.updateStatus = 'failed';
                        this.updateError = data.error || 'Failed to start update';
                    }
                } catch (e) {
                    // Even if network dropped briefly, start polling because agent might have started
                    this.startPolling();
                }
            },

            startPolling() {
                if (this.pollInterval) clearInterval(this.pollInterval);
                this.pollInterval = setInterval(() => this.checkStatus(), 1500);
            },

            async checkStatus() {
                try {
                    const resp = await fetch('{{ route('admin.updates.status') }}');
                    if (!resp.ok) return;

                    const data = await resp.json();
                    const state = data.state || data.status;

                    if (data.running || state === 'running') {
                        this.updating = true;
                        this.updateFinished = false;
                        this.updateStatus = 'running';
                        this.updateProgress = data.progress || Math.max(this.updateProgress, 15);
                        this.updateStep = data.step || 'Processing...';
                        this.refreshLog();
                        if (!this.pollInterval) this.startPolling();
                    } else if (state === 'completed') {
                        if (this.updating) {
                            this.updating = false;
                            this.updateFinished = true;
                            this.updateStatus = 'completed';
                            this.updateProgress = 100;
                            this.updateStep = data.step || 'Update finished successfully!';
                            this.refreshLog();
                            if (this.pollInterval) {
                                clearInterval(this.pollInterval);
                                this.pollInterval = null;
                            }
                        }
                    } else if (state === 'failed') {
                        if (this.updating) {
                            this.updating = false;
                            this.updateFinished = true;
                            this.updateStatus = 'failed';
                            this.updateError = data.error || 'Update process reported failure';
                            this.updateStep = data.step || 'Failed';
                            this.refreshLog();
                            if (this.pollInterval) {
                                clearInterval(this.pollInterval);
                                this.pollInterval = null;
                            }
                        }
                    }
                } catch (e) {}
            },

            async refreshLog() {
                if (this.logLoading) return;
                this.logLoading = true;
                try {
                    const res = await fetch('{{ route('admin.updates.log') }}');
                    if (res.ok) {
                        const json = await res.json();
                        this.log = json.log || '';
                        this.lastRefreshed = new Date().toLocaleTimeString();
                        this.$nextTick(() => this.scrollToBottom());
                    }
                } catch (e) {}
                this.logLoading = false;
            },

            scrollToBottom() {
                const box = this.$refs.logBox;
                if (box) box.scrollTop = box.scrollHeight;
            }
        }
    }
    </script>
</x-admin-layout>
