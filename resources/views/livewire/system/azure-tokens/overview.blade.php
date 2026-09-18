@php
    use App\Models\AzureCredentialSweep;

    $stats = $this->stats;
    $lastSweep = $this->lastSweep;
    $lastSuccessful = $this->lastSuccessfulSweep;

    $cards = [
        ['label' => 'Credentials tracked', 'value' => $stats['total'], 'tone' => 'text-surface-fg'],
        ['label' => 'Expiring within '.\App\Enums\AzureCredentialStatus::WARNING_DAYS.' days', 'value' => $stats['expiring'], 'tone' => $stats['expiring'] > 0 ? 'text-warning' : 'text-surface-fg'],
        ['label' => 'Already expired', 'value' => $stats['expired'], 'tone' => $stats['expired'] > 0 ? 'text-danger' : 'text-surface-fg'],
        ['label' => 'Acknowledged', 'value' => $stats['acknowledged'], 'tone' => 'text-muted'],
    ];
@endphp

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-surface-fg">Azure Token Expiry</h2>
                <p class="mt-1 text-sm text-muted">
                    Client secrets and certificates on every Entra app registration and service
                    principal in the tenant. Read-only: Mission Control never writes to Azure.
                </p>
            </div>

            <div class="flex items-center gap-3">
                @if($this->credentialsConfigured)
                    <button type="button" wire:click="sweepNow" wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-surface border border-border rounded-md font-semibold text-xs text-surface-fg-soft uppercase tracking-widest shadow-sm hover:bg-surface-2 disabled:opacity-50">
                        Sweep now
                    </button>
                @endif
                <a href="{{ route('system.integrations') }}"
                   class="text-sm text-info hover:underline">Credentials</a>
            </div>
        </div>

        @if(! $this->credentialsConfigured)
            <x-alert-warning
                title="Entra ID is not configured"
                description="Add the tenant ID, client ID and client secret on the Microsoft Entra ID
                             tile under System → Integrations. Until then nothing is swept and this
                             page stays empty." />
        @elseif(! $this->sweepsEnabled)
            <x-alert-warning
                title="Sweeps are switched off"
                description="Credentials are stored but the daily sweep will not run. Turn on
                             'Run the daily sweep' on the Microsoft Entra ID tile under
                             System → Integrations." />
        @elseif($this->isStale)
            <x-alert-danger
                title="Credential data is stale"
                :description="'No sweep has completed in the last '.AzureCredentialSweep::STALE_AFTER_HOURS.' hours,
                              so the figures below may no longer reflect the tenant. The usual cause is the
                              watcher\'s own client secret having expired — check the connection test on the
                              Entra ID tile under System → Integrations.'" />
        @endif

        @if($sweepQueued)
            <x-alert-info
                title="Sweep queued"
                description="A sweep has been queued. Reload this page in a moment to see the result." />
        @endif

        @if($lastSweep && $lastSweep->status === AzureCredentialSweep::STATUS_FAILED)
            <x-alert-danger
                title="The last sweep failed"
                :description="$lastSweep->error ?? 'No reason was recorded.'" />
        @endif

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($cards as $card)
                <div class="rounded-lg border border-border bg-surface-2 px-4 py-5 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted">{{ $card['label'] }}</dt>
                    <dd class="mt-2 text-3xl font-semibold {{ $card['tone'] }}">{{ number_format($card['value']) }}</dd>
                </div>
            @endforeach
        </div>

        <div class="mt-4 text-sm text-muted">
            @if($lastSuccessful)
                Last completed sweep
                <span class="font-medium text-surface-fg-soft" title="{{ $lastSuccessful->started_at }} UTC">
                    {{ $lastSuccessful->started_at->diffForHumans() }}
                </span>
                &middot; {{ number_format($lastSuccessful->applications) }} app registrations,
                {{ number_format($lastSuccessful->service_principals) }} service principals,
                {{ number_format($lastSuccessful->credentials_seen) }} credentials
                @if($lastSuccessful->credentials_removed > 0)
                    &middot; {{ number_format($lastSuccessful->credentials_removed) }} no longer in Azure
                @endif
            @else
                No sweep has completed yet.
            @endif
        </div>

    </div>
</div>
