@php
    use Illuminate\Support\Facades\Auth;
@endphp
<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900 ">

        <a wire:click="$toggle('isOpen')"  href="#">
            <img class="h-12 grayscale " src="/images/stripe.svg" alt="Stripe">
        </a>

    </div>

    @if($isOpen)
        <div class="">
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">
                    <img class="h-12 rounded-sm bg-gray-800 " src="/images/stripe.svg" alt="Stripe">
                    <br>
                    Stripe Billing API


                </x-slot>
                <x-slot name="content">
                    <ul class="list-disc list-inside my-4">
                        <li class="pl-4">Find your <strong>Testing Secret Key</strong> from Stripe's dashboard and enter it below</li>
                        <li class="pl-4">Find your <strong>Production Secret Key</strong> from Stripe's dashboard and enter it below</li>
                        <li class="pl-4">Navigate to <a class="font-semibold hover:underline" href="/utilities/card-processing">Card Processing utility</a> to process TBS export files.</li>
                    </ul>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="stripe_secret_test_key" value="{{ __('Test Secret Key') }}" />
                        <x-input id="stripe_secret_test_key" type="text" class="mt-1 block w-full " wire:model.live="state.stripe_secret_test_key" />
                        <x-input-error for="state.stripe_secret_test_key" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="stripe_secret_prod_key" value="{{ __(' Production Secret Key') }}" />
                        <x-input id="stripe_secret_prod_key" type="text" class="mt-1 block w-full " wire:model.live="state.stripe_secret_prod_key" />
                        <x-input-error for="state.stripe_secret_prod_key" class="mt-2" />
                    </div>

                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="saveStripeKeys"  wire:loading.attr="disabled">
                       Save
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif


</div>
