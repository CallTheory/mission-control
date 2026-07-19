<x-form-section submit="saveMarketingWebsite">
    <x-slot name="title">
        {{ __('Company Marketing Site') }}
    </x-slot>

    <x-slot name="description">
        Enter your company or team's marketing or internal site i.e., <code class="break-all font-semibold">https://yourdomain.com/</code>
    </x-slot>

    <x-slot name="form">
        <x-form-field for="marketing_site" label="{{ __('Endpoint URL') }}"
            error-for="state.marketing_site" wire:model.live="state.marketing_site" />
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
