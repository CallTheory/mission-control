@php
    $parseSecret = config('services.inbound_email.parse_secret');
    $forwardSecret = config('services.inbound_email.forward_secret');
    $parseUrl = $parseSecret ? secure_url('/webhooks/sendgrid/parse/'.$parseSecret) : null;
@endphp
<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">
        <a wire:click="$toggle('isOpen')" href="#">
            <img class="h-12 rounded-sm grayscale" src="/images/sendgrid.svg" alt="SendGrid">
        </a>
    </div>

    @if($isOpen)
        <x-dialog-modal wire:model.live="isOpen">
            <x-slot name="title">
                <img class="h-12 bg-gray-800 rounded-sm" src="/images/sendgrid.svg" alt="SendGrid">
                <br>
                SendGrid Inbound Parse API
            </x-slot>
            <x-slot name="content">
                <ul class="list-disc list-inside my-4">
                    <li class="pl-4">Choose a subdomain for inbound email, i.e. <strong>inbound-email.yourdomain.com</strong></li>
                    <li class="pl-4"><a class="font-semibold hover:underline" href="https://sendgrid.com/docs/for-developers/parsing-email/setting-up-the-inbound-parse-webhook/#setting-up-an-mx-record">Setup a DNS MX record</a> for the domain pointing at SendGrid.</li>
                    <li class="pl-4">Use the <strong>Destination Url</strong> at <a class="font-semibold hover:underline" href="https://app.sendgrid.com/settings/parse">https://app.sendgrid.com/settings/parse</a></li>
                </ul>

                <x-label>Destination URL</x-label>
                @if($parseUrl)
                    <code class="block break-all rounded-md shadow my-4 p-4 bg-black text-white">{{ $parseUrl }}</code>
                @else
                    <x-alert-warning
                        title="Parse webhook secret not configured"
                        description="Set INBOUND_EMAIL_PARSE_SECRET in the environment to generate the destination URL." />
                @endif

                <x-label>Scripting API Key</x-label>
                @if($forwardSecret)
                    <code class="block break-all rounded-md shadow my-4 p-4 bg-black text-white">{{ $forwardSecret }}</code>
                @else
                    <x-alert-warning
                        title="Forward API key not configured"
                        description="Set INBOUND_EMAIL_FORWARD_SECRET in the environment to enable the agent forward endpoint." />
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-button class="ml-2" wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                    Got it
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
