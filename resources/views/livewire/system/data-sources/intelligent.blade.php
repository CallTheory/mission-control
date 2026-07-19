<x-form-section submit="saveIntelligentConnection">
    <x-slot name="title">
        {{ __('Intelligent Series Database Connection') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Enter the database connection information for your Intelligent database.') }}
    </x-slot>

    <x-slot name="form">
        <x-form-field for="is_db_host" label="{{ __('Intelligent Database Host Server') }}"
            error-for="state.is_db_host" wire:model.live="state.is_db_host" />

        <x-form-field for="is_db_port" label="{{ __('Intelligent Database Port') }}"
            error-for="state.is_db_port" wire:model.live="state.is_db_port" />

        <x-form-field for="is_db_data" label="{{ __('Intelligent Default Database') }}"
            error-for="state.is_db_data" wire:model.live="state.is_db_data" />

        <x-form-field for="is_db_user" label="{{ __('Intelligent Database Username') }}"
            error-for="state.is_db_user" wire:model.live="state.is_db_user" />

        <x-form-field for="is_db_pass" label="{{ __('Intelligent Database Password') }}" type="password"
            error-for="state.is_db_pass" wire:model.live="state.is_db_pass" />

        <x-form-field for="is_db_pass_confirmation" label="{{ __('Confirm Password') }}" type="password"
            error-for="state.is_db_pass_confirmation" wire:model.live="state.is_db_pass_confirmation" />
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
