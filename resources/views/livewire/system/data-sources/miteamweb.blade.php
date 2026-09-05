<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Intelligent Series miTeamWeb Site') }}
    </x-slot>

    <x-slot name="description">
        Enter your company or team's <strong>miTeamWeb</strong> site i.e., <code class="break-all font-semibold">https://app.yourdomain.com/miteamweb</code>
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
