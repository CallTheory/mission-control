<x-form-section submit="save">
    <x-slot name="title">
        {{ __('Fax Notification Settings') }}
    </x-slot>

    <x-slot name="description">
        If you utilize mFax or RingCentral for ISFax integration, you can configure where notifications are sent for submission failures and file buildup in the processing folders.
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
