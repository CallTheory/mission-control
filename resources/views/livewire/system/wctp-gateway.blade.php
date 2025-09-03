<div>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @if (session()->has('message'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">WCTP SMS Gateway Configuration</h2>
                
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Simplified SMS Relay System</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>This WCTP gateway provides basic SMS relay functionality:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Accept WCTP SubmitRequest messages and send via Twilio</li>
                                    <li>Route inbound SMS to Enterprise Hosts based on phone number</li>
                                    <li>Manage Enterprise Hosts with authentication</li>
                                </ul>
                                <p class="mt-2">
                                    <strong>WCTP Endpoint:</strong> 
                                    <code class="bg-white px-2 py-1 rounded">{{ $wctpEndpoint }}</code>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button wire:click="$set('activeTab', 'overview')"
                                class="{{ $activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Overview
                        </button>
                        <button wire:click="$set('activeTab', 'hosts')"
                                class="{{ $activeTab === 'hosts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Enterprise Hosts
                        </button>
                        <button wire:click="$set('activeTab', 'twilio')"
                                class="{{ $activeTab === 'twilio' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} cursor-pointer whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Twilio Status
                        </button>
                    </nav>
                </div>

                <!-- Overview Tab -->
                @if($activeTab === 'overview')
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Quick Start Guide</h3>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                                <li><strong>Configure Twilio:</strong> Set up your Twilio credentials in <a href="{{ route('system.data-sources') }}" class="text-blue-600 hover:underline">Data Sources</a></li>
                                <li><strong>Create Enterprise Hosts:</strong> Add hosts below or use the <a href="{{ route('utilities.enterprise-hosts') }}" class="text-blue-600 hover:underline">Enterprise Host Management</a> page</li>
                                <li><strong>Assign Phone Numbers:</strong> Map Twilio phone numbers to each Enterprise Host</li>
                                <li><strong>Send WCTP Messages:</strong> POST WCTP XML to <code class="bg-white px-1 rounded">{{ $wctpEndpoint }}</code></li>
                            </ol>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Example WCTP Message</h3>
                            <pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs overflow-x-auto"><code>&lt;?xml version="1.0"?&gt;
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
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Add New Enterprise Host</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <input type="text" wire:model="newHost.name" 
                                           class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                                           placeholder="My Enterprise Host">
                                    @error('newHost.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sender ID</label>
                                    <input type="text" wire:model="newHost.senderID" 
                                           class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                                           placeholder="UNIQUE_ID">
                                    @error('newHost.senderID') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Security Code</label>
                                    <div class="flex gap-2">
                                        <input type="text" wire:model="newHost.securityCode" 
                                               class="flex-1 border-gray-300 rounded-md shadow-sm text-sm"
                                               placeholder="Min 8 characters">
                                        <button type="button" wire:click="generateSecurityCode"
                                                class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm">
                                            Generate
                                        </button>
                                    </div>
                                    @error('newHost.securityCode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Callback URL (Optional)</label>
                                    <input type="url" wire:model="newHost.callback_url" 
                                           class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                                           placeholder="https://example.com/wctp">
                                    @error('newHost.callback_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="mt-4">
                                <button wire:click="addEnterpriseHost" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                    Add Enterprise Host
                                </button>
                            </div>
                        </div>

                        <!-- Existing Hosts List -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Existing Enterprise Hosts</h3>
                            @if($enterpriseHosts->isEmpty())
                                <p class="text-gray-500 text-sm">No enterprise hosts configured yet.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($enterpriseHosts as $host)
                                        <div class="border rounded-lg p-4 {{ !$host->enabled ? 'bg-gray-50' : '' }}">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h4 class="font-semibold">{{ $host->name }}</h4>
                                                    <p class="text-sm text-gray-600">
                                                        Sender ID: <code class="bg-gray-100 px-1 rounded">{{ $host->senderID }}</code>
                                                        @if($host->callback_url)
                                                            | Callback: <span class="text-xs">{{ $host->callback_url }}</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button wire:click="toggleEnterpriseHost({{ $host->id }})"
                                                            class="px-3 py-1 {{ $host->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }} rounded text-sm">
                                                        {{ $host->enabled ? 'Enabled' : 'Disabled' }}
                                                    </button>
                                                    @if(!$host->messages()->exists())
                                                        <button wire:click="removeEnterpriseHost({{ $host->id }})"
                                                                onclick="return confirm('Are you sure?')"
                                                                class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                                                            Delete
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <!-- Phone Numbers -->
                                            <div class="mt-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Phone Numbers (comma-separated)
                                                </label>
                                                <div class="flex gap-2">
                                                    <input type="text" 
                                                           wire:model="hostPhoneNumbers.{{ $host->id }}"
                                                           placeholder="+12025551234, +13035555678"
                                                           class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                                                    <button wire:click="updateHostPhoneNumbers({{ $host->id }})"
                                                            class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                                        Update
                                                    </button>
                                                </div>
                                                @if($host->phone_numbers && count($host->phone_numbers) > 0)
                                                    <div class="mt-1 text-xs text-gray-600">
                                                        Current: {{ implode(', ', $host->phone_numbers) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                <p class="text-sm text-yellow-800">
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
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">Twilio Configured</h3>
                                        <p class="mt-1 text-sm text-green-700">
                                            Twilio credentials are configured in Data Sources. The gateway can send and receive SMS messages.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Twilio Not Configured</h3>
                                        <p class="mt-1 text-sm text-red-700">
                                            Please configure Twilio credentials in 
                                            <a href="{{ route('system.data-sources') }}" class="underline">System Settings → Data Sources</a>
                                        </p>
                                        <ul class="mt-2 list-disc list-inside text-sm text-red-600">
                                            <li>Twilio Account SID</li>
                                            <li>Twilio Auth Token</li>
                                            <li>Twilio From Number</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Integration Details</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">WCTP Endpoint:</dt>
                                    <dd class="font-mono text-gray-900">{{ $wctpEndpoint }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Inbound SMS Webhook:</dt>
                                    <dd class="font-mono text-gray-900">{{ url('/wctp/twilio/incoming') }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Status Callback URL:</dt>
                                    <dd class="font-mono text-gray-900">{{ url('/wctp/twilio/callback/{messageId}') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>