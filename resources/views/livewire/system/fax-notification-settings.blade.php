<x-form-section submit="saveFaxNotificationSettings" >
    <x-slot name="title">
        {{ __('Fax Notification Settings') }}
    </x-slot>

    <x-slot name="description">
        If you utilize mFax or RingCentral for ISFax integration, you can configure where notifications are sent for submission failures and file buildup in the processing folders.
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="fax_failure_notification_email" value="{{ __('Fax Failure Notification Email') }}" />
            <x-input id="fax_failure_notification_email" type="text" class="mt-1 block w-full " wire:model.live="state.fax_failure_notification_email" />
            <small class="text-xs text-gray-400 0">
                Email address for fax submission failures. For normal fax failures, please use the built-in notifications in your mFax or RingCentral portal.
            </small>
            <x-input-error for="state.fax_failure_notification_email" class="mt-2" />
        </div>`

        <div class="col-span-6 sm:col-span-4">
            <x-label for="fax_buildup_notification_email" value="{{ __('Fax Failure Buildup Email') }}" />
            <x-input id="fax_buildup_notification_email" type="text" class="mt-1 block w-full " wire:model.live="state.fax_buildup_notification_email" />
            <small class="text-xs text-gray-400 0">
                Email address to send notifications when the fax processing folders have files older than 15 minutes. This indicates that ISFax or one of the mFax/RingCentral integrations is not processing files.
            </small>
            <x-input-error for="state.fax_buildup_notification_email" class="mt-2" />
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
