@section('title', 'WCTP Gateway')
<x-app-layout>
    <x-slot name="header">
        <x-system.wctp-header />
    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-surface shadow-xl sm:rounded-lg p-6">
                <h2 class="text-2xl font-bold text-surface-fg">WCTP SMS Gateway</h2>
                <p class="mt-2 max-w-3xl text-sm text-surface-fg-soft">
                    Relays WCTP messages to and from SMS through Twilio, Bandwidth or Com.io. Enterprise hosts submit
                    WCTP XML to <code class="bg-surface-2 px-1 rounded">{{ url('/wctp') }}</code>; replies and delivery
                    receipts come back through each carrier's webhooks.
                </p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @can(\App\Enums\Capability::WctpManage->value)
                        <a href="{{ route('system.wctp.gateway') }}"
                           class="block p-4 rounded-lg border border-border bg-surface-2 hover:bg-surface-inverse-hover transition">
                            <h3 class="font-semibold text-surface-fg">Gateway</h3>
                            <p class="mt-1 text-sm text-muted">
                                The endpoint clients post to, what it supports, and a test send.
                            </p>
                        </a>

                        <a href="{{ route('system.wctp.carriers') }}"
                           class="block p-4 rounded-lg border border-border bg-surface-2 hover:bg-surface-inverse-hover transition">
                            <h3 class="font-semibold text-surface-fg">Carriers</h3>
                            <p class="mt-1 text-sm text-muted">
                                Which carriers can send, their webhook URLs, and the default carrier.
                            </p>
                        </a>

                        <a href="{{ route('system.wctp.enterprise-hosts') }}"
                           class="block p-4 rounded-lg border border-border bg-surface-2 hover:bg-surface-inverse-hover transition">
                            <h3 class="font-semibold text-surface-fg">Enterprise Hosts</h3>
                            <p class="mt-1 text-sm text-muted">
                                Sender IDs, security codes, callback URLs, and each host's phone numbers and carriers.
                            </p>
                        </a>
                    @endcan

                    @can(\App\Enums\Capability::WctpMessages->value)
                        <a href="{{ route('system.wctp.messages') }}"
                           class="block p-4 rounded-lg border border-border bg-surface-2 hover:bg-surface-inverse-hover transition">
                            <h3 class="font-semibold text-surface-fg">Messages</h3>
                            <p class="mt-1 text-sm text-muted">
                                Every message in and out, with delivery status and a retry for failures.
                            </p>
                        </a>
                    @endcan
                </div>
            </div>

            @can(\App\Enums\Capability::WctpManage->value)
                <div class="bg-surface shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-3">Quick Start Guide</h3>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-surface-fg-soft">
                        <li><strong>Configure a carrier:</strong> Twilio, Bandwidth or Com.io credentials in <a href="{{ route('system.integrations') }}" class="text-info hover:underline">Integrations</a></li>
                        <li><strong>Point the carrier at us:</strong> paste the webhook URLs from <a href="{{ route('system.wctp.carriers') }}" class="text-info hover:underline">Carriers</a> into the carrier's portal</li>
                        <li><strong>Create Enterprise Hosts:</strong> add them on <a href="{{ route('system.wctp.enterprise-hosts') }}" class="text-info hover:underline">Enterprise Hosts</a>, listing each host's numbers and the carrier that owns each one</li>
                        <li><strong>Send WCTP Messages:</strong> POST WCTP XML to <code class="bg-surface-2 px-1 rounded">{{ url('/wctp') }}</code></li>
                    </ol>

                    <h3 class="text-lg font-semibold mt-6 mb-3">Example WCTP Message</h3>
                    <pre class="bg-surface-inverse text-subtle p-3 rounded text-xs overflow-x-auto"><code>&lt;?xml version="1.0"?&gt;
&lt;wctp-Operation wctpVersion="1.3"&gt;
    &lt;wctp-SubmitRequest&gt;
        &lt;wctp-SubmitHeader&gt;
            &lt;wctp-ClientOriginator senderID="YOUR_SENDER_ID" securityCode="YOUR_CODE"/&gt;
            &lt;wctp-Recipient recipientID="5551234567"/&gt;
            &lt;wctp-MessageControl messageID="msg123"/&gt;
        &lt;/wctp-SubmitHeader&gt;
        &lt;wctp-Payload&gt;
            &lt;wctp-Alphanumeric&gt;Your SMS message&lt;/wctp-Alphanumeric&gt;
        &lt;/wctp-Payload&gt;
    &lt;/wctp-SubmitRequest&gt;
&lt;/wctp-Operation&gt;</code></pre>
                </div>
            @endcan

        </div>
    </div>
</x-app-layout>
