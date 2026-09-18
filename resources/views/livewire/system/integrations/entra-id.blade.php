<div>
    <button type="button" wire:click="mountAction('configure')"
        class="col-span-1 w-full flex justify-center py-8 px-8 bg-surface-inverse hover:bg-surface-inverse-hover cursor-pointer"
        title="Configure Microsoft Entra ID">
        <img class="h-12 rounded-sm grayscale" src="/images/entra-id.svg" alt="Microsoft Entra ID">
    </button>

    @if($this->isConfigured())
        <div class="px-2 py-1 flex flex-wrap items-center gap-2 text-xs">
            @if($this->isEnabled())
                <span class="text-success">Token watcher enabled</span>
            @else
                <span class="text-muted">Configured, sweeps off</span>
            @endif

            <button type="button" wire:click="testConnection" wire:loading.attr="disabled"
                    class="text-info hover:underline disabled:opacity-50">
                test
            </button>

            <a href="{{ route('system.azure-tokens') }}" class="text-info hover:underline">dashboard</a>
        </div>

        @if($testResult)
            <p class="px-2 pb-1 text-xs text-success">{{ $testResult }}</p>
        @endif

        @if($testError)
            <p class="px-2 pb-1 text-xs text-danger">{{ $testError }}</p>
        @endif
    @endif

    <x-filament-actions::modals />
</div>
