<x-form-section submit="saveIntelligentUser">
    <x-slot name="title">
        {{ __('Intelligent Series Service Account') }}
    </x-slot>

    <x-slot name="description">
        Enter an Intelligent Series Agent username and password for use with the ISWeb API. We recommend you create a new Intelligent Series Agent service account with strong, random password.
    </x-slot>

    <x-slot name="form">
        <x-form-field for="is_username" label="{{ __('Intelligent Series Agent Username') }}"
            error-for="state.is_username" wire:model.live="state.is_username" />

        <x-form-field for="is_password" label="{{ __('Intelligent Series Agent Password') }}" type="password"
            error-for="state.is_password" wire:model.live="state.is_password" />

        <x-form-field for="is_password_confirmation" label="{{ __('Password Confirmation') }}" type="password"
            error-for="state.is_password_confirmation" wire:model.live="state.is_password_confirmation" />
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
