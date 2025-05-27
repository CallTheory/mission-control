<x-form-section submit="saveIntelligentUser">
    <x-slot name="title">
        {{ __('Intelligent Series Service Account') }}
    </x-slot>

    <x-slot name="description">
        Enter an Intelligent Series Agent username and password for use with the ISWeb API. We recommend you create a new Intelligent Series Agent service account with strong, random password.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_username" value="{{ __('Intelligent Series Agent Username') }}" />
            <x-input id="is_username" type="text" class="mt-1 block w-full " wire:model.live="state.is_username" />
            <x-input-error for="state.is_username" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_password" value="{{ __('Intelligent Series Agent Password') }}" />
            <x-input id="is_password" type="password" class="mt-1 block w-full " wire:model.live="state.is_password" />
            <x-input-error for="state.is_password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="is_password_confirmation" value="{{ __('Password Confirmation') }}" />
            <x-input id="is_password_confirmation" type="password" class="mt-1 block w-full " wire:model.live="state.is_password_confirmation" />
            <x-input-error for="state.is_password_confirmation" class="mt-2" />
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
