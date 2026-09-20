@php
    $parseSecret = config('services.inbound_email.parse_secret');
    $forwardSecret = config('services.inbound_email.forward_secret');
    $parseUrl = $parseSecret ? secure_url('/webhooks/sendgrid/parse/'.$parseSecret) : null;
@endphp
<div>
                <ul class="list-disc list-inside my-4">
                    <li class="pl-4">Choose a subdomain for inbound email, i.e. <strong>inbound-email.yourdomain.com</strong></li>
                    <li class="pl-4"><a class="font-semibold hover:underline" href="https://sendgrid.com/docs/for-developers/parsing-email/setting-up-the-inbound-parse-webhook/#setting-up-an-mx-record">Setup a DNS MX record</a> for the domain pointing at SendGrid.</li>
                    <li class="pl-4">Use the <strong>Destination Url</strong> at <a class="font-semibold hover:underline" href="https://app.sendgrid.com/settings/parse">https://app.sendgrid.com/settings/parse</a></li>
                </ul>

                <x-label>Destination URL</x-label>
                @if($parseUrl)
                    <code class="block break-all rounded-md shadow my-4 p-4 bg-surface-inverse text-surface-inverse-fg">{{ $parseUrl }}</code>
                @else
                    <x-alert-warning
                        title="Parse webhook secret not configured"
                        description="Set INBOUND_EMAIL_PARSE_SECRET in the environment to generate the destination URL. Upgrading an install that already had inbound email working? The old URL used a value derived from APP_URL -- run `php artisan inbound-email:backfill-secrets` to recover it so SendGrid keeps delivering." />
                @endif

                <x-label>Scripting API Key</x-label>
                @if($forwardSecret)
                    <code class="block break-all rounded-md shadow my-4 p-4 bg-surface-inverse text-surface-inverse-fg">{{ $forwardSecret }}</code>
                @else
                    <x-alert-warning
                        title="Forward API key not configured"
                        description="Set INBOUND_EMAIL_FORWARD_SECRET in the environment to enable the agent forward endpoint. Existing agent scripts send a key derived from APP_URL -- run `php artisan inbound-email:backfill-secrets` to recover it rather than re-keying every script." />
                @endif
</div>
