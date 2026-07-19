<x-form-section submit="saveMiTeamWeb">
    <x-slot name="title">
        {{ __('Intelligent Series miTeamWeb Site') }}
    </x-slot>

    <x-slot name="description">
        Enter your company or team's <strong>miTeamWeb</strong> site i.e., <code class="break-all font-semibold">https://app.yourdomain.com/miteamweb</code>
    </x-slot>

    <x-slot name="form">
        <x-form-field for="miteamweb_site" label="{{ __('Endpoint URL') }}"
            error-for="state.miteamweb_site" wire:model.live="state.miteamweb_site" />
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
