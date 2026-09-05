<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Intelligent Series Service Account') }}
    </x-slot>

    <x-slot name="description">
        Enter an Intelligent Series Agent username and password for use with the ISWeb API. We recommend you create a new Intelligent Series Agent service account with strong, random password.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6">
            {{ $this->form }}
        </div>
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
