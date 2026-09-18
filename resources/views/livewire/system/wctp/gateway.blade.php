<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg">
        <div class="p-6">
            <h2 class="text-2xl font-semibold text-surface-fg mb-4">Gateway</h2>
            
            <div class="mb-6">
                <p class="text-surface-fg-soft mb-4">
                    This simplified WCTP gateway provides SMS relay functionality. It accepts basic WCTP SubmitRequest messages
                    from Enterprise Hosts and forwards them as SMS through Twilio, Bandwidth or Commio -- whichever carrier owns
                    the sending number. Inbound SMS messages are routed back to the appropriate Enterprise Host based on phone
                    number mapping.
                </p>
            </div>

            @if (! $this->isConfigured())
                <div class="mb-6 p-4 bg-warning-soft border border-warning rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning">Carrier Configuration Required</h3>
                            <div class="mt-2 text-sm text-warning">
                                <p>No SMS carrier is configured yet. To enable SMS relay functionality:</p>
                                <ol class="list-decimal list-inside mt-2 text-sm space-y-1">
                                    <li><strong>Set up a carrier:</strong> configure Twilio, Bandwidth or Commio in <a href="{{ route('system.integrations') }}" class="underline">Integrations</a></li>
                                    <li><strong>Create Enterprise Hosts:</strong> go to <a href="{{ route('system.wctp.enterprise-hosts') }}" class="underline">Enterprise Hosts</a></li>
                                    <li><strong>Map phone numbers:</strong> list each host's numbers and pick the carrier that owns each one</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 bg-success-soft border border-success rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-success">Carrier Connected Successfully</h3>
                            <div class="mt-2 text-sm text-success">
                                <p>
                                    The WCTP gateway is ready to relay SMS messages via
                                    {{ implode(', ', $this->testProviderOptions()) }}.
                                    Numbers with no carrier assigned use {{ $this->defaultProvider()->label() }}.
                                </p>
                                <p class="mt-1 text-xs text-success">
                                    <strong>Note:</strong> This is a simplified SMS relay. Only basic WCTP SubmitRequest for SMS is supported.
                                    Manage your <a href="{{ route('system.wctp.enterprise-hosts') }}" class="underline">Enterprise Hosts</a>
                                    and <a href="{{ route('system.wctp.carriers') }}" class="underline">Carriers</a> separately.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">WCTP Endpoint Configuration</h3>
                <div class="bg-surface-2 p-4 rounded-lg">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-surface-fg-soft mb-1">WCTP Endpoint URL</label>
                        <div class="flex items-center">
                            <input type="text" readonly value="{{ $wctpEndpoint }}" 
                                   class="flex-1 bg-surface border-border rounded-md shadow-sm text-sm">
                            <button onclick="navigator.clipboard.writeText('{{ $wctpEndpoint }}')"
                                    class="ml-2 px-3 py-2 border border-border rounded-md text-sm bg-surface hover:bg-surface-2">
                                Copy
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-muted">
                            Configure your WCTP client to send messages to this endpoint via HTTP POST.
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-surface-fg-soft mb-1">Supported Features</label>
                        <ul class="list-disc list-inside text-sm text-surface-fg-soft">
                            <li><strong>wctp-SubmitRequest:</strong> Send SMS to phone numbers</li>
                            <li><strong>Inbound SMS:</strong> Automatically routed to Enterprise Host callback URL</li>
                            <li><strong>Phone Number Mapping:</strong> Each host can have multiple assigned numbers, each on its own carrier</li>
                            <li><strong>Authentication:</strong> senderID and securityCode validation</li>
                        </ul>
                        <p class="mt-2 text-sm text-muted">
                            <em>Note: Only basic SMS relay is supported. Advanced WCTP operations are not implemented.</em>
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-fg-soft mb-1">Protocol Version</label>
                        <p class="text-sm text-surface-fg-soft">WCTP v1.3</p>
                    </div>
                </div>
            </div>

            @if ($this->isConfigured())
                <div class="mb-6">
                    <button wire:click="toggleTestPanel" 
                            class="px-4 py-2 bg-info text-info-fg rounded-md hover:bg-info-hover transition">
                        {{ $showTestPanel ? 'Hide' : 'Show' }} Test Panel
                    </button>
                </div>

                @if ($showTestPanel)
                    <div class="mb-6 p-4 bg-info-soft rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">Test Message Sending</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="testProvider" class="block text-sm font-medium text-surface-fg-soft mb-1">
                                    Carrier
                                </label>
                                <select wire:model="testProvider" id="testProvider"
                                        class="w-full border-border rounded-md shadow-sm text-sm">
                                    @foreach ($this->testProviderOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('testProvider')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="testRecipient" class="block text-sm font-medium text-surface-fg-soft mb-1">
                                    Recipient Phone Number
                                </label>
                                <input type="tel" wire:model="testRecipient" id="testRecipient"
                                       placeholder="10 digit phone number (e.g., 5551234567)"
                                       class="w-full border-border rounded-md shadow-sm text-sm">
                                @error('testRecipient')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="testMessage" class="block text-sm font-medium text-surface-fg-soft mb-1">
                                    Message Content
                                </label>
                                <textarea wire:model="testMessage" id="testMessage" rows="3"
                                          placeholder="Enter your test message (max 160 characters)"
                                          maxlength="160"
                                          class="w-full border-border rounded-md shadow-sm text-sm"></textarea>
                                <p class="mt-1 text-sm text-muted">
                                    {{ strlen($testMessage) }}/160 characters
                                </p>
                                @error('testMessage')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <button wire:click="sendTestMessage"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 bg-success text-success-fg rounded-md hover:bg-success-hover transition disabled:opacity-50">
                                    <span wire:loading.remove>Send Test Message</span>
                                    <span wire:loading>Sending...</span>
                                </button>
                            </div>

                            @if ($testResult)
                                <div class="p-3 rounded-md {{ str_starts_with($testResult, '✓') ? 'bg-success-soft text-success-soft-fg' : 'bg-danger-soft text-danger-soft-fg' }}">
                                    {{ $testResult }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endif

            <div class="mt-6 p-4 bg-surface-2 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">Example WCTP Submit Request</h3>
                <p class="text-sm text-surface-fg-soft mb-3">
                    Send this XML to the endpoint above to relay an SMS. The senderID must match a configured Enterprise Host.
                </p>
                <pre class="bg-surface-inverse text-subtle p-4 rounded-md overflow-x-auto text-xs"><code>&lt;?xml version="1.0"?&gt;
&lt;!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd"&gt;
&lt;wctp-Operation wctpVersion="1.3"&gt;
    &lt;wctp-SubmitRequest&gt;
        &lt;wctp-SubmitHeader&gt;
            &lt;wctp-ClientOriginator senderID="YOUR_SENDER_ID" securityCode="YOUR_SECURITY_CODE"/&gt;
            &lt;wctp-Recipient recipientID="5551234567"/&gt;
            &lt;wctp-MessageControl messageID="msg123"/&gt;
        &lt;/wctp-SubmitHeader&gt;
        &lt;wctp-Payload&gt;
            &lt;wctp-Alphanumeric&gt;Your SMS message text here&lt;/wctp-Alphanumeric&gt;
        &lt;/wctp-Payload&gt;
    &lt;/wctp-SubmitRequest&gt;
&lt;/wctp-Operation&gt;</code></pre>
                <p class="text-xs text-muted mt-2">
                    The message will be sent from the phone number(s) assigned to your Enterprise Host, through the
                    carrier that number belongs to.
                </p>
            </div>
        </div>
    </div>
</div>