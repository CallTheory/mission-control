<div>
    <x-flash />

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-filter-select for="filterStatus" label="Status" wire-model="filterStatus" placeholder="All Statuses">
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
        </x-filter-select>

        <x-filter-select for="filterUser" label="User" wire-model="filterUser" :placeholder="null">
            <option value="0">All Users</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </x-filter-select>
    </div>

    <x-table>
        <x-slot name="head">
            <x-table.heading>User</x-table.heading>
            <x-table.heading>Date Range</x-table.heading>
            <x-table.heading>Active Filters</x-table.heading>
            <x-table.heading>Records</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Exported At</x-table.heading>
            <x-table.heading>Actions</x-table.heading>
        </x-slot>

        @forelse ($logs as $log)
            @php
                $filters = $log->filters ?? [];
                $activeCount = collect($filters)->except(['start_date', 'end_date', 'sort_by', 'sort_direction', 'has_any'])->filter(fn ($v) => !is_null($v) && $v !== '' && $v !== false)->count();
            @endphp
            <x-table.row>
                <x-table.cell>{{ $log->user?->name ?? 'Deleted User' }}</x-table.cell>
                <x-table.cell muted>
                    @if(!empty($filters['start_date']) && !empty($filters['end_date']))
                        {{ \Carbon\Carbon::parse($filters['start_date'])->format('M j, Y g:ia') }} &mdash; {{ \Carbon\Carbon::parse($filters['end_date'])->format('M j, Y g:ia') }}
                    @else
                        &mdash;
                    @endif
                </x-table.cell>
                <x-table.cell muted>{{ $activeCount }} filter{{ $activeCount !== 1 ? 's' : '' }}</x-table.cell>
                <x-table.cell muted>{{ $log->result_count }}</x-table.cell>
                <x-table.cell>
                    @if($log->status === 'completed')
                        <x-status-badge status="completed" />
                    @else
                        <x-status-badge status="failed" :title="$log->error_message" />
                    @endif
                </x-table.cell>
                <x-table.cell muted>{{ $log->created_at->format('M j, Y g:ia') }}</x-table.cell>
                <x-table.cell>
                    <button wire:click="reexport({{ $log->id }})" class="text-primary hover:underline">
                        Re-export
                    </button>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7">No export history found.</x-table.empty>
        @endforelse

        <x-slot name="footer">{{ $logs->links() }}</x-slot>
    </x-table>
</div>
