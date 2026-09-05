<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Intelligent Series Inbound SMTP') }}
    </x-slot>

    <x-slot name="description">
        Enter the <strong>hostname</strong> for your intelligent server i.e., <code class="break-all whitespace-nowrap font-semibold">is.yourdomain.com</code> and the associated port, typically <code class="bg-surface-2 px-2 py-1 rounded">25</code>.
        You can find the information in Intelligent Series Supervisor &rarr; System &rarr; Email &rarr; Inbound SMTP settings.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6">
            {{ $this->form }}
        </div>
    </x-slot>

    <x-slot name="actions">

        <x-secondary-button type="button" class="mr-3" wire:click="testConnection" wire:loading.attr="disabled">
            {{ __('Test Connection') }}
        </x-secondary-button>

        @if($connectionStatus === 'success')
            <span class="mr-3 text-sm text-success">{{ $connectionMessage }}</span>
        @elseif($connectionStatus === 'failed')
            <span class="mr-3 text-sm text-danger">{{ $connectionMessage }}</span>
        @endif
        <x-action-message class="mr-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
