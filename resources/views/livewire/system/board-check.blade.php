<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Board Check Configuration') }}
    </x-slot>

    <x-slot name="description">
        Set the system-level configuration values for the <a class="font-semibold hover:text-primary transition transform duration-700 ease-in-out" href="/utilities/board-check">Board Check Utility</a> functionality.
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
