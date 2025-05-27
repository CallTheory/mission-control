<x-form-section submit="saveSwitchTimezone">
    <x-slot name="title">
        {{ __('Switch Data Timezone') }}
    </x-slot>

    <x-slot name="description">
        Select the timezone that your switch (call) data is stored as. This is typically the timezone assigned to your <strong>Intelligent</strong> SQL server.
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="timezone" value="{{ __('Select a timezone:') }}" />
            <x-input id="timezone" type="text" list="timezones" class="mt-1 block w-full " wire:model.defer="state.timezone" />
            <datalist id="timezones">
                @include('timezones')
            </datalist>
            <x-input-error for="state.timezone" class="mt-2" />
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
