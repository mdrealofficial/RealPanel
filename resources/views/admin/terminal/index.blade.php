<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-gray-900 border border-gray-800 flex items-center justify-center text-green-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Root Web Terminal</h2>
                    <p class="text-xs text-gray-500">Direct interactive bash shell with root privileges</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span>
                    root@{{ gethostname() }}
                </span>
                <button type="button" onclick="document.getElementById('terminal-frame').src = document.getElementById('terminal-frame').src;"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-sm transition">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reconnect
                </button>
                <button type="button" onclick="toggleFullscreen()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded-lg shadow-sm transition">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    Fullscreen
                </button>
            </div>
        </div>
    </x-slot>

    <div id="terminal-container" class="bg-gray-950 rounded-2xl shadow-xl border border-gray-800 overflow-hidden flex flex-col" style="height: calc(100vh - 200px); min-height: 550px;">
        <!-- Terminal Header Bar -->
        <div class="px-4 py-2.5 bg-gray-900 border-b border-gray-800 flex items-center justify-between text-xs text-gray-400 select-none">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span>
                <span class="ml-2 font-mono text-gray-300">bash — root</span>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono">
                <span class="text-green-400">● Interactive PTY</span>
                <span class="text-gray-500">UTF-8</span>
            </div>
        </div>

        <!-- Terminal Iframe running ttyd via Nginx proxy -->
        <div class="flex-1 w-full bg-[#111827] relative">
            <iframe id="terminal-frame"
                    src="{{ $terminalUrl }}"
                    class="w-full h-full border-0 absolute inset-0"
                    allow="clipboard-read; clipboard-write"></iframe>
        </div>
    </div>

    <script>
        function toggleFullscreen() {
            const container = document.getElementById('terminal-container');
            if (!document.fullscreenElement) {
                container.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable fullscreen: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }
    </script>
</x-admin-layout>
