<x-form-section submit="save">
    <x-slot name="title">
        Intelligent Series Web API Endpoint
    </x-slot>

    <x-slot name="description">
        Enter the <strong>https</strong> ISWeb endpoint for your mobileIS.svc i.e., <code class="break-all font-semibold">https://yourdomain.com/isweb/mobileIS.svc</code>
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
