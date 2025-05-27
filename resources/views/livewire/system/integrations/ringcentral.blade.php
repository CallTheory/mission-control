<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900 ">

        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale   mr-2" src="/images/ring-central.svg"
                 alt="Ring Central">
        </a>
    </div>

    @if($isOpen)
        <div class="">
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">

                    <div class="flex text-4xl text-gray-900 font-extrabold">
                        <img class="h-12 rounded  mr-2" src="/images/ring-central.svg"
                             alt="Ring Central">
                    </div>

                    RingCentral Fax API

                </x-slot>
                <x-slot name="content">
                    <strong class="">Ring Central minimum required setup:</strong>
                    <ul class="list-disc list-inside mb-4">
                        <li class="pl-4">Create a new RingCentral Fax developer account</li>
                        <li class="pl-4">Generate an API client to be used.</li>
                        <li class="pl-4">Generate a JWT token from user credentials.</li>
                        <li class="pl-4">Enter the <strong>RingCentral Client ID</strong> into the field below.</li>
                        <li class="pl-4">Enter the <strong>RingCentral Client Secret</strong> into the field below.</li>
                        <li class="pl-4">Enter the <strong>RingCentral JWT Token</strong> into the field below.</li>
                        <li class="pl-4">Enter the <strong>RingCentral API Endpoint</strong> into the field below.</li>
                    </ul>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="ringcentral_client_id" value="{{ __('Ringcentral Client ID') }}"/>
                        <x-input id="ringcentral_client_id" type="text" class="mt-1 block w-full "
                                     wire:model.live="state.ringcentral_client_id"/>
                        <x-input-error for="state.ringcentral_client_id" class="mt-2"/>
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="ringcentral_client_secret" value="{{ __('Ringcentral Client Secret') }}"/>
                        <x-input id="ringcentral_client_secret" type="password"
                                     class="mt-1 block w-full "
                                     wire:model.live="state.ringcentral_client_secret"/>
                        <x-input-error for="state.ringcentral_client_secret" class="mt-2"/>
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="ringcentral_jwt_token" value="{{ __('Ringcentral JWT Token') }}"/>
                        <x-input id="ringcentral_jwt_token" type="password"
                                     class="mt-1 block w-full "
                                     wire:model.live="state.ringcentral_jwt_token"/>
                        <x-input-error for="state.ringcentral_jwt_token" class="mt-2"/>
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="ringcentral_api_endpoint" value="{{ __('Ringcentral API Endpoint') }}"/>
                        <x-input id="ringcentral_api_endpoint" type="text" class="mt-1 block w-full "
                                     wire:model.live="state.ringcentral_api_endpoint"/>
                        <x-input-error for="state.ringcentral_api_endpoint" class="mt-2"/>
                    </div>

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
        </div>
    @endif


</div>
