{{--
    Faxes this installation knows failed.

    Separate from the provider history above it: a fax whose submission failed never
    reached the provider, so it is absent from that list however far back you look. Until
    this existed the failure email was the only evidence such a fax had ever been sent.
--}}
@php($failures = $this->faxFailures())

@if(count($failures))
    <div class="bg-surface my-4 rounded-sm border border-border shadow">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-lg font-medium leading-6 text-surface-fg">Failed Faxes</h3>
            <p class="mt-1 text-sm text-muted">
                Faxes that did not go out. A submission failure never reached the provider, so it
                will not appear in the provider's own list above.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-muted">
                        <th class="px-6 py-3">Account</th>
                        <th class="px-6 py-3">To</th>
                        <th class="px-6 py-3">File</th>
                        <th class="px-6 py-3">Failed</th>
                        <th class="px-6 py-3">Reason</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-soft bg-surface">
                    @foreach($failures as $failure)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-surface-fg">{{ $failure['account'] }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-mono text-xs text-surface-fg">{{ $failure['phone'] }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-mono text-xs text-muted">{{ $failure['file'] }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-muted">
                                @if($failure['failed_at'])
                                    {{ \Carbon\Carbon::parse($failure['failed_at'])->timezone(auth()->user()->timezone ?? 'UTC')->format('m/d/Y g:i A') }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-surface-fg">{{ $failure['reason'] ?: '—' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs">
                                @if($failure['retryable'])
                                    <span wire:click="mountAction('retryFax', { fax: {{ $failure['id'] }} })"
                                          class="cursor-pointer font-medium text-primary hover:underline">
                                        Send Again
                                    </span>
                                @else
                                    {{-- The provider has this one; use its own Resend above. --}}
                                    <span class="text-muted">Provider rejected</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
