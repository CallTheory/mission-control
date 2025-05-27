<x-form-section submit="updateAmtelcoSMTPDetails">
    <x-slot name="title">
        {{ __('Intelligent Series Inbound SMTP') }}
    </x-slot>

    <x-slot name="description">
        Enter the <strong>hostname</strong> for your intelligent server i.e., <code class="break-all whitespace-nowrap font-semibold">is.yourdomain.com</code> and the associated port, typically <code class="bg-gray-200 px-2 py-1 rounded">25</code>.
        You can find the information in Intelligent Series Supervisor &rarr; System &rarr; Email &rarr; Inbound SMTP settings.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="amtelco_inbound_smtp_host" value="{{ __('IS SMTP Email Host') }}" />
            <x-input id="amtelco_inbound_smtp_host" type="text" class="mt-1 block w-full " wire:model.live="state.amtelco_inbound_smtp_host" />
            <x-input-error for="state.amtelco_inbound_smtp_host" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="amtelco_inbound_smtp_port" value="{{ __('IS SMTP Email Port') }}" />
            <x-input id="amtelco_inbound_smtp_port" type="text" class="mt-1 block w-full " wire:model.live="state.amtelco_inbound_smtp_port" />
            <x-input-error for="state.amtelco_inbound_smtp_port" class="mt-2" />
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
