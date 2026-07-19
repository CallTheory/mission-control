<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">
        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale mr-2" src="/images/twilio.svg" alt="Twilio">
        </a>
    </div>

    @if($isOpen)
        <x-dialog-modal wire:model.live="isOpen">
            <x-slot name="title">
                <div class="flex text-4xl text-surface-fg font-extrabold">
                    <img class="h-12 rounded mr-2" src="/images/twilio.svg" alt="Twilio">
                </div>
                Twilio Configuration
            </x-slot>
            <x-slot name="content">
                <p class="mb-4">Configure Twilio credentials for SMS messaging and WCTP gateway integration. These credentials enable SMS forwarding and other Twilio-powered features.</p>

                <x-form-field for="twilio_account_sid" label="Account SID" error-for="state.twilio_account_sid"
                    wire:model="state.twilio_account_sid" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                    help="Your Twilio Account SID from the Twilio Console" />

                <x-form-field for="twilio_auth_token" label="Auth Token" type="password" error-for="state.twilio_auth_token"
                    wire:model="state.twilio_auth_token" placeholder="********************************"
                    help="Your Twilio Auth Token (keep this secret)" />

                <x-form-field for="twilio_from_number" label="From Phone Number" error-for="state.twilio_from_number"
                    wire:model="state.twilio_from_number" placeholder="+15551234567"
                    help="Your Twilio phone number to send SMS from (E.164 format)" />

                @if(($state['twilio_account_sid'] ?? '') && ($state['twilio_auth_token'] ?? '') && ($state['twilio_from_number'] ?? ''))
                    <x-alert-success
                        title="Twilio is configured and ready to use"
                        description="SMS messaging and WCTP gateway features are enabled" />
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                    Cancel
                </x-secondary-button>

                <x-button class="ml-2" wire:click="save" wire:loading.attr="disabled">
                    Save
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
