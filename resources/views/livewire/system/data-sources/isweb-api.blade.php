<x-form-section submit="saveISWebAPIConnection">
    <x-slot name="title">
        {{ __('Intelligent Series Web API Endpoint') }}
    </x-slot>

    <x-slot name="description">
        Enter the <strong>https</strong> ISWeb endpoint for your mobileIS.svc i.e., <code class="break-all  font-semibold">https://yourdomain.com/isweb/mobileIS.svc</code>
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="isweb_api_endpoint" value="{{ __('Endpoint URL') }}" />
            <x-input id="isweb_api_endpoint" type="text" class="mt-1 block w-full " wire:model.live="state.isweb_api_endpoint" />
            <x-input-error for="state.isweb_api_endpoint" class="mt-2" />
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
