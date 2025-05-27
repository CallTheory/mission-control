<x-form-section submit="saveMarketingWebsite">
    <x-slot name="title">
        {{ __('Company Marketing Site') }}
    </x-slot>

    <x-slot name="description">
        Enter your company or team's marketing or internal site i.e., <code class="break-all  font-semibold">https://yourdomain.com/</code>
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="marketing_site" value="{{ __('Endpoint URL') }}" />
            <x-input id="marketing_site" type="text" class="mt-1 block w-full " wire:model.live="state.marketing_site" />
            <x-input-error for="state.marketing_site" class="mt-2" />
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
