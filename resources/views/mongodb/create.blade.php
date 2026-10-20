<x-user-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <a href="{{ route('user.mongodb.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-800">Create MongoDB Database</h2>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('user.mongodb.store') }}" method="POST">
        @csrf
        <div class="max-w-xl space-y-6">

            <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
                @if($accounts->count() > 1)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Account</label>
                        <select name="account_id"
                            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                    {{ $account->username }} ({{ $account->server->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="account_id" value="{{ $accounts->first()?->id }}">
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Database Name</label>
                    <input type="text" name="db_name" value="{{ old('db_name') }}"
                        placeholder="mydb"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1.5 text-xs text-gray-400">Letters, numbers, underscores only. Your account prefix will be added automatically.</p>
                </div>
            </div>

            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
                <h4 class="text-sm font-semibold text-emerald-800 mb-2">What gets created</h4>
                <ul class="text-xs text-emerald-700 space-y-1.5 list-disc list-inside">
                    <li>A MongoDB NoSQL database instance</li>
                    <li>A dedicated user with <code class="font-mono bg-emerald-100 px-1 py-0.5 rounded">readWrite</code> role on the database</li>
                    <li>Ready-to-use connection string for Node.js, Python, PHP, or Go</li>
                    <li>The password is shown once — save it immediately</li>
                </ul>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Create Database
                </button>
                <a href="{{ route('user.mongodb.index') }}"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>

        </div>
    </form>
</x-user-layout>
