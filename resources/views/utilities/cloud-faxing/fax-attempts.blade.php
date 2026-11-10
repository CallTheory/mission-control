{{--
    The calls RingCentral made trying to deliver one failed fax (Details on Failed Faxes).
    $details comes from App\Services\Faxing\RingCentralFaxAttempts.
--}}
<div class="space-y-4 text-sm">
    @if($fax)
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs">
            <dt class="text-muted">To</dt>
            <dd class="font-mono text-surface-fg">{{ $fax->phone }}</dd>
            <dt class="text-muted">Account</dt>
            <dd class="text-surface-fg">{{ $fax->accountLabel() ?? 'Unknown' }}</dd>
            <dt class="text-muted">RingCentral said</dt>
            <dd class="text-surface-fg">{{ $fax->failure_reason ?: '—' }}</dd>
        </dl>
    @endif

    @if($details['error'])
        <div class="rounded-sm border border-warning bg-warning-soft px-4 py-3 text-warning">
            {{ $details['error'] }}
        </div>
    @elseif($details['attempts'] === [])
        <p class="text-muted">
            RingCentral has no calls on record for this fax yet. Its call log can lag a few minutes
            behind a failure; open this again shortly.
        </p>
    @else
        @if($details['hint'])
            <div class="rounded-sm border border-warning bg-warning-soft px-4 py-3 text-warning">
                {{ $details['hint'] }}
            </div>
        @endif

        <table class="min-w-full divide-y divide-border">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wider text-muted">
                    <th class="py-2 pr-4">#</th>
                    <th class="py-2 pr-4">Dialled</th>
                    <th class="py-2 pr-4">Call length</th>
                    <th class="py-2">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-soft">
                @foreach($details['attempts'] as $attempt)
                    <tr>
                        <td class="py-2 pr-4 text-xs text-muted">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap py-2 pr-4 text-xs text-surface-fg">
                            {{ $attempt['started_at'] ? \Carbon\Carbon::parse($attempt['started_at'])->timezone($timezone)->format('m/d/Y g:i:s A') : '—' }}
                        </td>
                        <td class="whitespace-nowrap py-2 pr-4 text-xs text-surface-fg">{{ $attempt['duration'] }}s</td>
                        <td class="py-2 text-xs text-surface-fg">
                            {{ $attempt['result'] }}
                            @if($attempt['reason'])
                                <span class="text-muted">({{ $attempt['reason'] }})</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($details['fetched_at'])
        <p class="text-xs text-muted">
            From RingCentral's call log, fetched
            {{ \Carbon\Carbon::parse($details['fetched_at'])->timezone($timezone)->format('g:i A') }}.
        </p>
    @endif
</div>
