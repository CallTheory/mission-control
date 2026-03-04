<div>
    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="text-sm text-green-700">
                    {{ session('message') }}
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="text-sm text-red-700">
                    {{ session('error') }}
                </div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <label for="filterStatus" class="block text-sm font-medium text-gray-700">Status</label>
            <select wire:model.live="filterStatus" id="filterStatus" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <div>
            <label for="filterUser" class="block text-sm font-medium text-gray-700">User</label>
            <select wire:model.live="filterUser" id="filterUser" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="0">All Users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date Range</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Active Filters</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Records</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Exported At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($logs as $log)
                    @php
                        $filters = $log->filters ?? [];
                        $activeCount = collect($filters)->except(['start_date', 'end_date', 'sort_by', 'sort_direction', 'has_any'])->filter(fn ($v) => !is_null($v) && $v !== '' && $v !== false)->count();
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                            {{ $log->user?->name ?? 'Deleted User' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            @if(!empty($filters['start_date']) && !empty($filters['end_date']))
                                {{ \Carbon\Carbon::parse($filters['start_date'])->format('M j, Y g:ia') }} &mdash; {{ \Carbon\Carbon::parse($filters['end_date'])->format('M j, Y g:ia') }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $activeCount }} filter{{ $activeCount !== 1 ? 's' : '' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $log->result_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @if($log->status === 'completed')
                                <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Completed</span>
                            @else
                                <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800" @if($log->error_message) title="{{ $log->error_message }}" @endif>Failed</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $log->created_at->format('M j, Y g:ia') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <button
                                wire:click="reexport({{ $log->id }})"
                                class="text-indigo-600 hover:text-indigo-900"
                            >
                                Re-export
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                            No export history found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
