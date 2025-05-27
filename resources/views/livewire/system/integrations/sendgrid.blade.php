@php
    use Illuminate\Support\Facades\Auth;
@endphp
<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900 ">

        <a wire:click="$toggle('isOpen')"  href="#">
            <img class="h-12 rounded-sm grayscale " src="/images/sendgrid.svg" alt="SendGrid">
        </a>

    </div>

    @if($isOpen)
        <div class="">
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">

                        <img class="h-12 bg-gray-800 rounded-sm " src="/images/sendgrid.svg" alt="SendGrid">
                    <br>

                    SendGrid Inbound Parse API


                </x-slot>
                <x-slot name="content">
                    <ul class="list-disc list-inside my-4">
                        <li class="pl-4">Choose a subdomain for inbound email, i.e. <strong>inbound-email.yourdomain.com</strong></li>
                        <li class="pl-4"><a class="font-semibold hover:underline" href="https://sendgrid.com/docs/for-developers/parsing-email/setting-up-the-inbound-parse-webhook/#setting-up-an-mx-record">Setup a DNS MX record</a> for the domain pointing at SendGrid.</li>
                        <li class="pl-4">Use the <strong>Destination Url</strong> at <a class="font-semibold hover:underline" href="https://app.sendgrid.com/settings/parse">https://app.sendgrid.com/settings/parse</a></li>
                    </ul>

                    <x-label for="sendgrid_parse_destination_url">
                        Destination URL
                    </x-label>

                    <code class="inline-flex break-all rounded-md shadow inline-flex my-4 p-4 bg-black  text-white ">
                        {{ secure_url('/webhooks/sendgrid/parse', [ hash('md5', config('app.url') ) ] ) }}
                    </code>

                    <x-label for="scripting_api_key">
                        Scripting API Key
                    </x-label>

                    <code class="inline-flex break-all rounded-md shadow inline-flex my-4 p-4 bg-black  text-white  ">
                        {{ hash('sha256', config('app.url') ) }}
                    </code>
                </x-slot>

                <x-slot name="footer">
                    <!--
                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>
                    -->

                    <x-button class="ml-2" wire:click="$toggle('isOpen')"  wire:loading.attr="disabled">
                       Got it
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif


</div>
