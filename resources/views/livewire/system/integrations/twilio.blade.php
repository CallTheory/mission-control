<x-form-section submit="save">
    <x-slot name="title">
        <div class="flex items-center">
            <img class="h-8 mr-3" src="/images/twilio.svg" alt="Twilio">
            Twilio Configuration
        </div>
    </x-slot>

    <x-slot name="description">
        Configure Twilio credentials for SMS messaging and WCTP gateway integration. These credentials enable SMS forwarding and other Twilio-powered features.
    </x-slot>

    <x-slot name="form">
        <!-- Account SID -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="twilio_account_sid" value="Account SID" />
            <x-input id="twilio_account_sid" type="text" class="mt-1 block w-full" 
                     wire:model="twilio_account_sid" 
                     placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" />
            <x-input-error for="twilio_account_sid" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Your Twilio Account SID from the Twilio Console</p>
        </div>

        <!-- Auth Token -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="twilio_auth_token" value="Auth Token" />
            <x-input id="twilio_auth_token" type="password" class="mt-1 block w-full" 
                     wire:model="twilio_auth_token" 
                     placeholder="********************************" />
            <x-input-error for="twilio_auth_token" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Your Twilio Auth Token (keep this secret)</p>
        </div>

        <!-- From Number -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="twilio_from_number" value="From Phone Number" />
            <x-input id="twilio_from_number" type="text" class="mt-1 block w-full" 
                     wire:model="twilio_from_number" 
                     placeholder="+15551234567" />
            <x-input-error for="twilio_from_number" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Your Twilio phone number to send SMS from (E.164 format)</p>
        </div>

        @if($twilio_account_sid && $twilio_auth_token && $twilio_from_number)
            <div class="col-span-6 sm:col-span-4">
                <div class="rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                Twilio is configured and ready to use
                            </p>
                            <p class="text-sm text-green-700 mt-1">
                                SMS messaging and WCTP gateway features are enabled
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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