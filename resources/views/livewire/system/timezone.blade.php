<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Switch Data Timezone') }}
    </x-slot>

    <x-slot name="description">
        Select the timezone that your switch (call) data is stored as. This is typically the timezone assigned to your <strong>Intelligent</strong> SQL server.
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
