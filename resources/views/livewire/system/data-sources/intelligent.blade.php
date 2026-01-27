<x-form-section submit="saveIntelligentConnection">
    <x-slot name="title">
        {{ __('Intelligent Series Database Connection') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Enter the database connection information for your Intelligent database.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_host" value="{{ __('Intelligent Database Host Server') }}" />
            <x-input id="is_db_host" type="text" class="mt-1 block w-full " wire:model.live="state.is_db_host" />
            <x-input-error for="state.is_db_host" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_port" value="{{ __('Intelligent Database Port') }}" />
            <x-input id="is_db_port" type="text" class="mt-1 block w-full " wire:model.live="state.is_db_port" />
            <x-input-error for="state.is_db_port" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_data" value="{{ __('Intelligent Default Database') }}" />
            <x-input id="is_db_data" type="text" class="mt-1 block w-full " wire:model.live="state.is_db_data" />
            <x-input-error for="state.is_db_data" class="mt-2" />
        </div>


        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_user" value="{{ __('Intelligent Database Username') }}" />
            <x-input id="is_db_user" type="text" class="mt-1 block w-full " wire:model.live="state.is_db_user" />
            <x-input-error for="state.is_db_user" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_pass" value="{{ __('Intelligent Database Password') }}" />
            <x-input id="is_db_pass" type="password" class="mt-1 block w-full " wire:model.live="state.is_db_pass" />
            <x-input-error for="state.is_db_pass" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_db_pass_confirmation" value="{{ __('Confirm Password') }}" />
            <x-input id="is_db_pass_confirmation" type="password" class="mt-1 block w-full " wire:model.live="state.is_db_pass_confirmation" />
            <x-input-error for="state.is_db_pass_confirmation" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="actions">
        @if ($connectionStatus === 'success')
            <span class="mr-3 text-sm text-green-600">{{ $connectionMessage }}</span>
        @elseif ($connectionStatus === 'failed')
            <span class="mr-3 text-sm text-red-600">{{ $connectionMessage }}</span>
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
