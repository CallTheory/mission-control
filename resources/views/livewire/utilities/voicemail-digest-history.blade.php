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
                <option value="queued">Queued</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="no_recordings">No Recordings</option>
            </select>
        </div>

        <div>
            <label for="filterSchedule" class="block text-sm font-medium text-gray-700">Schedule</label>
            <select wire:model.live="filterSchedule" id="filterSchedule" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="0">All Schedules</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->id }}">{{ $schedule->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Schedule</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date Range</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Recipients</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Recordings</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sent At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                            {{ $log->voicemailDigest?->name ?? 'Deleted Schedule' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $log->start_date->format('M j, Y g:ia') }} &mdash; {{ $log->end_date->format('M j, Y g:ia') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            <span title="{{ implode(', ', $log->recipients ?? []) }}">
                                {{ count($log->recipients ?? []) }} recipient{{ count($log->recipients ?? []) !== 1 ? 's' : '' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $log->recording_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @switch($log->status)
                                @case('queued')
                                    <span class="inline-flex rounded-full bg-yellow-100 px-2 text-xs font-semibold leading-5 text-yellow-800">Queued</span>
                                    @break
                                @case('sent')
                                    <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Sent</span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800" @if($log->error_message) title="{{ $log->error_message }}" @endif>Failed</span>
                                    @break
                                @case('no_recordings')
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-800">No Recordings</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $log->sent_at?->format('M j, Y g:ia') ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @if($log->voicemailDigest)
                                <button
                                    wire:click="resend({{ $log->id }})"
                                    wire:confirm="Are you sure you want to resend this digest?"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    Resend
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
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
