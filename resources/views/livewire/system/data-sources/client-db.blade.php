<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Client Database Connection') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Enter the database connection information for your client database.') }}
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
