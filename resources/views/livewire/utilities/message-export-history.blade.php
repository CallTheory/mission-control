<div>
    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-success-soft p-4">
            <div class="flex">
                <div class="text-sm text-success">
                    {{ session('message') }}
                </div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <label for="filterStatus" class="block text-sm font-medium text-surface-fg-soft">Status</label>
            <select wire:model.live="filterStatus" id="filterStatus" class="mt-1 block rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="">All Statuses</option>
                <option value="queued">Queued</option>
                <option value="completed">Completed</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="no_messages">No Messages</option>
            </select>
        </div>

        <div>
            <label for="filterExport" class="block text-sm font-medium text-surface-fg-soft">Export</label>
            <select wire:model.live="filterExport" id="filterExport" class="mt-1 block rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="0">All Exports</option>
                @foreach ($exports as $export)
                    <option value="{{ $export->id }}">{{ $export->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border-soft">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Export</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Date Range</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Messages</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Run By</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Run At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Download</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft bg-surface">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-surface-fg">
                            {{ $log->export_name }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->client_number }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->start_date->format('M j, Y g:ia') }} &mdash; {{ $log->end_date->format('M j, Y g:ia') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->message_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @switch($log->status)
                                @case('queued')
                                    <span class="inline-flex rounded-full bg-warning-soft px-2 text-xs font-semibold leading-5 text-warning-soft-fg">Queued</span>
                                    @break
                                @case('completed')
                                    <span class="inline-flex rounded-full bg-success-soft px-2 text-xs font-semibold leading-5 text-success-soft-fg">Completed</span>
                                    @break
                                @case('sent')
                                    <span class="inline-flex rounded-full bg-info-soft px-2 text-xs font-semibold leading-5 text-info-soft-fg">Sent</span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex rounded-full bg-danger-soft px-2 text-xs font-semibold leading-5 text-danger-soft-fg" @if($log->error_message) title="{{ $log->error_message }}" @endif>Failed</span>
                                    @break
                                @case('no_messages')
                                    <span class="inline-flex rounded-full bg-surface-2 px-2 text-xs font-semibold leading-5 text-surface-fg">No Messages</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->user?->name ?? 'Scheduled' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->created_at->format('M j, Y g:ia') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @if($log->file_path && in_array($log->status, ['completed']))
                                <a href="{{ route('utilities.message-export.download', $log) }}" class="text-primary hover:text-primary">
                                    Download CSV
                                </a>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-muted">
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
