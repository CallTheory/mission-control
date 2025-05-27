<x-form-section submit="saveMiTeamWeb">
    <x-slot name="title">
        {{ __('Intelligent Series miTeamWeb Site') }}
    </x-slot>

    <x-slot name="description">
        Enter your company or team's <strong>miTeamWeb</strong> site i.e., <code class="break-all  font-semibold">https://app.yourdomain.com/miteamweb</code>
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="miteamweb_site" value="{{ __('Endpoint URL') }}" />
            <x-input id="miteamweb_site" type="text" class="mt-1 block w-full " wire:model.live="state.miteamweb_site" />
            <x-input-error for="state.miteamweb_site" class="mt-2" />
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
