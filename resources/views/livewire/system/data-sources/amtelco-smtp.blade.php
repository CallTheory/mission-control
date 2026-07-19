<x-form-section submit="updateAmtelcoSMTPDetails">
    <x-slot name="title">
        {{ __('Intelligent Series Inbound SMTP') }}
    </x-slot>

    <x-slot name="description">
        Enter the <strong>hostname</strong> for your intelligent server i.e., <code class="break-all whitespace-nowrap font-semibold">is.yourdomain.com</code> and the associated port, typically <code class="bg-surface-2 px-2 py-1 rounded">25</code>.
        You can find the information in Intelligent Series Supervisor &rarr; System &rarr; Email &rarr; Inbound SMTP settings.
    </x-slot>

    <x-slot name="form">
        <x-form-field for="amtelco_inbound_smtp_host" label="{{ __('IS SMTP Email Host') }}"
            error-for="state.amtelco_inbound_smtp_host" wire:model.live="state.amtelco_inbound_smtp_host" />

        <x-form-field for="amtelco_inbound_smtp_port" label="{{ __('IS SMTP Email Port') }}"
            error-for="state.amtelco_inbound_smtp_port" wire:model.live="state.amtelco_inbound_smtp_port" />
    </x-slot>

    <x-slot name="actions">
        @if ($connectionStatus === 'success')
            <span class="mr-3 text-sm text-success">{{ $connectionMessage }}</span>
        @elseif ($connectionStatus === 'failed')
            <span class="mr-3 text-sm text-danger">{{ $connectionMessage }}</span>
        @endif

        <x-action-message class="mr-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-secondary-button wire:click="testConnection" wire:loading.attr="disabled" class="mr-3">
            <span wire:loading.remove wire:target="testConnection">{{ __('Test Connection') }}</span>
            <span wire:loading wire:target="testConnection">{{ __('Testing...') }}</span>
        </x-secondary-button>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
