<x-form-section submit="saveClientConnection">
    <x-slot name="title">
        {{ __('Client Database Connection') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Enter the database connection information for your client database.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_host" value="{{ __('Client Database Host Server') }}" />
            <x-input id="client_db_host" type="text" class="mt-1 block w-full " wire:model.live="state.client_db_host" />
            <x-input-error for="state.client_db_host" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_port" value="{{ __('Client Database Port') }}" />
            <x-input id="client_db_port" type="text" class="mt-1 block w-full " wire:model.live="state.client_db_port" />
            <x-input-error for="state.client_db_port" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_data" value="{{ __('Client Default Database') }}" />
            <x-input id="client_db_data" type="text" class="mt-1 block w-full " wire:model.live="state.client_db_data" />
            <x-input-error for="state.client_db_data" class="mt-2" />
        </div>


        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_user" value="{{ __('Client Database Username') }}" />
            <x-input id="client_db_user" type="text" class="mt-1 block w-full " wire:model.live="state.client_db_user" />
            <x-input-error for="state.client_db_user" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_pass" value="{{ __('Client Database Password') }}" />
            <x-input id="client_db_pass" type="password" class="mt-1 block w-full " wire:model.live="state.client_db_pass" />
            <x-input-error for="state.client_db_pass" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="client_db_pass_confirmation" value="{{ __('Confirm Password') }}" />
            <x-input id="client_db_pass_confirmation" type="password" class="mt-1 block w-full " wire:model.live="state.client_db_pass_confirmation" />
            <x-input-error for="state.client_db_pass_confirmation" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
