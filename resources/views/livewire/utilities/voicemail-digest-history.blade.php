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

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-danger-soft p-4">
            <div class="flex">
                <div class="text-sm text-danger">
                    {{ session('error') }}
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
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="no_recordings">No Recordings</option>
            </select>
        </div>

        <div>
            <label for="filterSchedule" class="block text-sm font-medium text-surface-fg-soft">Schedule</label>
            <select wire:model.live="filterSchedule" id="filterSchedule" class="mt-1 block rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary">
                <option value="0">All Schedules</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->id }}">{{ $schedule->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border-soft">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Schedule</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Date Range</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Recipients</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Recordings</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Sent At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft bg-surface">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-surface-fg">
                            {{ $log->voicemailDigest?->name ?? 'Deleted Schedule' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->start_date->format('M j, Y g:ia') }} &mdash; {{ $log->end_date->format('M j, Y g:ia') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-muted">
                            <span title="{{ implode(', ', $log->recipients ?? []) }}">
                                {{ count($log->recipients ?? []) }} recipient{{ count($log->recipients ?? []) !== 1 ? 's' : '' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->recording_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @switch($log->status)
                                @case('queued')
                                    <span class="inline-flex rounded-full bg-warning-soft px-2 text-xs font-semibold leading-5 text-warning-soft-fg">Queued</span>
                                    @break
                                @case('sent')
                                    <span class="inline-flex rounded-full bg-success-soft px-2 text-xs font-semibold leading-5 text-success-soft-fg">Sent</span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex rounded-full bg-danger-soft px-2 text-xs font-semibold leading-5 text-danger-soft-fg" @if($log->error_message) title="{{ $log->error_message }}" @endif>Failed</span>
                                    @break
                                @case('no_recordings')
                                    <span class="inline-flex rounded-full bg-surface-2 px-2 text-xs font-semibold leading-5 text-surface-fg">No Recordings</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-muted">
                            {{ $log->sent_at?->format('M j, Y g:ia') ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @if($log->voicemailDigest)
                                <button
                                    wire:click="resend({{ $log->id }})"
                                    wire:confirm="Are you sure you want to resend this digest?"
                                    class="text-primary hover:text-primary"
                                >
                                    Resend
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-muted">
                            No digest history found.
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
