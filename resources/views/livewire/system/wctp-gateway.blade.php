<div>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-surface-fg mb-6">WCTP SMS Gateway Configuration</h2>
                
                <div class="mb-6 p-4 bg-info-soft border border-info rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-info">Simplified SMS Relay System</h3>
                            <div class="mt-2 text-sm text-info">
                                <p>This WCTP gateway provides basic SMS relay functionality:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Accept WCTP SubmitRequest messages and send via Twilio</li>
                                    <li>Route inbound SMS to Enterprise Hosts based on phone number</li>
                                    <li>Manage Enterprise Hosts with authentication</li>
                                </ul>
                                <p class="mt-2">
                                    <strong>WCTP Endpoint:</strong> 
                                    <code class="bg-surface px-2 py-1 rounded">{{ $wctpEndpoint }}</code>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-border-soft mb-6">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button wire:click="$set('activeTab', 'overview')"
                                class="{{ $activeTab === 'overview' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-surface-fg-soft hover:border-border' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Overview
                        </button>
                        <button wire:click="$set('activeTab', 'hosts')"
                                class="{{ $activeTab === 'hosts' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-surface-fg-soft hover:border-border' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Enterprise Hosts
                        </button>
                        <button wire:click="$set('activeTab', 'twilio')"
                                class="{{ $activeTab === 'twilio' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-surface-fg-soft hover:border-border' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Twilio Status
                        </button>
                    </nav>
                </div>

                <!-- Overview Tab -->
                @if($activeTab === 'overview')
                    <div class="space-y-4">
                        <div class="bg-surface-2 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Quick Start Guide</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-surface-fg-soft">
                                <li><strong>Configure Twilio:</strong> Set up your Twilio credentials in <a href="{{ route('system.data-sources') }}" class="text-info hover:underline">Data Sources</a></li>
                                <li><strong>Create Enterprise Hosts:</strong> Add hosts below or use the <a href="{{ route('utilities.enterprise-hosts') }}" class="text-info hover:underline">Enterprise Host Management</a> page</li>
                                <li><strong>Assign Phone Numbers:</strong> Map Twilio phone numbers to each Enterprise Host</li>
                                <li><strong>Send WCTP Messages:</strong> POST WCTP XML to <code class="bg-surface px-1 rounded">{{ $wctpEndpoint }}</code></li>
                            </ol>
                        </div>

                        <div class="bg-surface-2 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Example WCTP Message</h3>
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
                    </div>
                @endif

                <!-- Enterprise Hosts Tab -->
                @if($activeTab === 'hosts')
                    <div class="space-y-4">
                        <!-- Add New Host Form -->
                        <div class="bg-surface-2 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Add New Enterprise Host</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-surface-fg-soft mb-1">Name</label>
                                    <input type="text" wire:model="newHost.name" 
                                           class="w-full border-border rounded-md shadow-sm text-sm"
                                           placeholder="My Enterprise Host">
                                    @error('newHost.name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-fg-soft mb-1">Sender ID</label>
                                    <input type="text" wire:model="newHost.senderID" 
                                           class="w-full border-border rounded-md shadow-sm text-sm"
                                           placeholder="UNIQUE_ID">
                                    @error('newHost.senderID') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-fg-soft mb-1">Security Code</label>
                                    <div class="flex gap-2">
                                        <input type="text" wire:model="newHost.securityCode" 
                                               class="flex-1 border-border rounded-md shadow-sm text-sm"
                                               placeholder="Min 8 characters">
                                        <button type="button" wire:click="generateSecurityCode"
                                                class="px-3 py-1 bg-surface-3 text-surface-fg-soft rounded-md hover:bg-surface-3 text-sm">
                                            Generate
                                        </button>
                                    </div>
                                    @error('newHost.securityCode') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-fg-soft mb-1">Callback URL (Optional)</label>
                                    <input type="url" wire:model="newHost.callback_url" 
                                           class="w-full border-border rounded-md shadow-sm text-sm"
                                           placeholder="https://example.com/wctp">
                                    @error('newHost.callback_url') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="mt-4">
                                <button wire:click="addEnterpriseHost" 
                                        class="px-4 py-2 bg-info text-info-fg rounded-md hover:bg-info-hover text-sm">
                                    Add Enterprise Host
                                </button>
                            </div>
                        </div>

                        <!-- Existing Hosts List -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Existing Enterprise Hosts</h3>
                            @if($enterpriseHosts->isEmpty())
                                <p class="text-muted text-sm">No enterprise hosts configured yet.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($enterpriseHosts as $host)
                                        <div class="border rounded-lg p-4 {{ !$host->enabled ? 'bg-surface-2' : '' }}">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h4 class="font-semibold">{{ $host->name }}</h4>
                                                    <p class="text-sm text-surface-fg-soft">
                                                        Sender ID: <code class="bg-surface-2 px-1 rounded">{{ $host->senderID }}</code>
                                                        @if($host->callback_url)
                                                            | Callback: <span class="text-xs">{{ $host->callback_url }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button wire:click="toggleEnterpriseHost({{ $host->id }})"
                                                            class="px-3 py-1 {{ $host->enabled ? 'bg-success-soft text-success-soft-fg' : 'bg-surface-2 text-surface-fg-soft' }} rounded text-sm">
                                                        {{ $host->enabled ? 'Enabled' : 'Disabled' }}
                                                    </button>
                                                    @if(!$host->messages()->exists())
                                                        <button wire:click="removeEnterpriseHost({{ $host->id }})"
                                                                onclick="return confirm('Are you sure?')"
                                                                class="px-3 py-1 bg-danger-soft text-danger-soft-fg rounded text-sm hover:bg-danger-soft">
                                                            Delete
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <!-- Phone Numbers -->
                                            <div class="mt-3">
                                                <label class="block text-sm font-medium text-surface-fg-soft mb-1">
                                                    Phone Numbers (comma-separated)
                                                </label>
                                                <div class="flex gap-2">
                                                    <input type="text" 
                                                           wire:model="hostPhoneNumbers.{{ $host->id }}"
                                                           placeholder="+12025551234, +13035555678"
                                                           class="flex-1 border-border rounded-md shadow-sm text-sm">
                                                    <button wire:click="updateHostPhoneNumbers({{ $host->id }})"
                                                            class="px-3 py-1 bg-info text-info-fg rounded text-sm hover:bg-info-hover">
                                                        Update
                                                    </button>
                                                </div>
                                                @if($host->phone_numbers && count($host->phone_numbers) > 0)
                                                    <div class="mt-1 text-xs text-surface-fg-soft">
                                                        Current: {{ implode(', ', $host->phone_numbers) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            
                            <div class="mt-4 p-3 bg-warning-soft border border-warning rounded">
                                <p class="text-sm text-warning">
                                    <strong>Note:</strong> For full Enterprise Host management including detailed phone number configuration, 
                                    visit the <a href="{{ route('utilities.enterprise-hosts') }}" class="underline">Enterprise Host Management</a> page.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Twilio Status Tab -->
                @if($activeTab === 'twilio')
                    <div class="space-y-4">
                        @if($twilioConfigured)
                            <div class="bg-success-soft border border-success rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-success">Twilio Configured</h3>
                                        <p class="mt-1 text-sm text-success">
                                            Twilio credentials are configured in Data Sources. The gateway can send and receive SMS messages.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-danger-soft border border-danger rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-danger" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-danger">Twilio Not Configured</h3>
                                        <p class="mt-1 text-sm text-danger">
                                            Please configure Twilio credentials in 
                                            <a href="{{ route('system.data-sources') }}" class="underline">System Settings → Data Sources</a>
                                        </p>
                                        <ul class="mt-2 list-disc list-inside text-sm text-danger">
                                            <li>Twilio Account SID</li>
                                            <li>Twilio Auth Token</li>
                                            <li>Twilio From Number</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="bg-surface-2 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Integration Details</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-surface-fg-soft">WCTP Endpoint:</dt>
                                    <dd class="font-mono text-surface-fg">{{ $wctpEndpoint }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-fg-soft">Inbound SMS Webhook:</dt>
                                    <dd class="font-mono text-surface-fg">{{ url('/wctp/twilio/incoming') }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-surface-fg-soft">Status Callback URL:</dt>
                                    <dd class="font-mono text-surface-fg">{{ url('/wctp/twilio/callback/{messageId}') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>