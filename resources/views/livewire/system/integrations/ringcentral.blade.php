<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">
        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale mr-2" src="/images/ring-central.svg" alt="Ring Central">
        </a>
    </div>

    @if($isOpen)
        <x-dialog-modal wire:model.live="isOpen">
            <x-slot name="title">
                <div class="flex text-4xl text-surface-fg font-extrabold">
                    <img class="h-12 rounded mr-2" src="/images/ring-central.svg" alt="Ring Central">
                </div>
                RingCentral Fax API
            </x-slot>
            <x-slot name="content">
                <strong>Ring Central minimum required setup:</strong>
                <ul class="list-disc list-inside mb-4">
                    <li class="pl-4">Create a new RingCentral Fax developer account</li>
                    <li class="pl-4">Generate an API client to be used.</li>
                    <li class="pl-4">Generate a JWT token from user credentials.</li>
                    <li class="pl-4">Enter the <strong>RingCentral Client ID</strong> into the field below.</li>
                    <li class="pl-4">Enter the <strong>RingCentral Client Secret</strong> into the field below.</li>
                    <li class="pl-4">Enter the <strong>RingCentral JWT Token</strong> into the field below.</li>
                    <li class="pl-4">Enter the <strong>RingCentral API Endpoint</strong> into the field below.</li>
                </ul>

                <x-form-field for="ringcentral_client_id" label="{{ __('Ringcentral Client ID') }}"
                    error-for="state.ringcentral_client_id" wire:model.live="state.ringcentral_client_id" />

                <x-form-field for="ringcentral_client_secret" label="{{ __('Ringcentral Client Secret') }}" type="password"
                    error-for="state.ringcentral_client_secret" wire:model.live="state.ringcentral_client_secret" />

                <x-form-field for="ringcentral_jwt_token" label="{{ __('Ringcentral JWT Token') }}" type="password"
                    error-for="state.ringcentral_jwt_token" wire:model.live="state.ringcentral_jwt_token" />

                <x-form-field for="ringcentral_api_endpoint" label="{{ __('Ringcentral API Endpoint') }}"
                    error-for="state.ringcentral_api_endpoint" wire:model.live="state.ringcentral_api_endpoint" />
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                    Cancel
                </x-secondary-button>

                <x-button class="ml-2" wire:click="saveRingCentralFaxDetails" wire:loading.attr="disabled">
                    Save
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
