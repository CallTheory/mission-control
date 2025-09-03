<div>
    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">WCTP Message Log</h2>
        @if($host)
            @php
                $currentHost = $hosts->find($host);
            @endphp
            @if($currentHost)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Showing messages for: <strong>{{ $currentHost->name }}</strong> ({{ $currentHost->senderID }})
                </p>
            @endif
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
            <input wire:model.live="search" type="text" id="search" 
                   placeholder="Phone, message, or ID..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        
        <div>
            <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Enterprise Host</label>
            <select wire:model.live="host" id="host" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="">All Hosts</option>
                @foreach($hosts as $hostOption)
                    <option value="{{ $hostOption->id }}">{{ $hostOption->name }} ({{ $hostOption->senderID }})</option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select wire:model.live="filterStatus" id="filterStatus" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="queued">Queued</option>
                <option value="submitted">Submitted</option>
                <option value="delivered">Delivered</option>
                <option value="failed">Failed</option>
                <option value="undelivered">Undelivered</option>
            </select>
        </div>
        
        <div>
            <label for="filterDirection" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Direction</label>
            <select wire:model.live="filterDirection" id="filterDirection" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="">All Directions</option>
                <option value="outbound">Outbound</option>
                <option value="inbound">Inbound</option>
            </select>
        </div>
        
        <div>
            <label for="filterCarrier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Carrier</label>
            <select wire:model.live="filterCarrier" id="filterCarrier" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="">All Carriers</option>
                @foreach($carriers as $carrier)
                    <option value="{{ $carrier }}">{{ ucfirst($carrier) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Date Range Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="dateFrom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
            <input wire:model.live="dateFrom" type="date" id="dateFrom" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        
        <div>
            <label for="dateTo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
            <input wire:model.live="dateTo" type="date" id="dateTo" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
        </div>
        
        <div class="flex items-end">
            <button wire:click="exportMessages" 
                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    {{-- Messages Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Direction</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">From/To</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($messages as $message)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            {{ $message->created_at->format('Y-m-d H:i:s') }}
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $message->created_at->diffForHumans() }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            @if($message->enterpriseHost)
                                <div class="text-sm font-medium">{{ $message->enterpriseHost->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $message->enterpriseHost->senderID }}</div>
                            @else
                                <span class="text-gray-400">Unknown</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                   {{ $message->direction === 'outbound' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100' }}">
                                {{ ucfirst($message->direction) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <div><strong>To:</strong> {{ $message->to }}</div>
                            <div><strong>From:</strong> {{ $message->from }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                            <div class="max-w-xs truncate">
                                {{ Str::limit($message->message, 50) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                ID: {{ $message->wctp_message_id }}
                            </div>
                            @if($message->reply_with)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100 mt-1">
                                    Reply: {{ $message->reply_with }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                   @switch($message->status)
                                       @case('delivered')
                                           bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                           @break
                                       @case('failed')
                                       @case('undelivered')
                                           bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                           @break
                                       @case('submitted')
                                       @case('queued')
                                           bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                           @break
                                       @default
                                           bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                                   @endswitch">
                                {{ ucfirst($message->status) }}
                            </span>
                            @if($message->retry_count > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Retries: {{ $message->retry_count }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button wire:click="viewMessage({{ $message->id }})" 
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                View
                            </button>
                            @if(in_array($message->status, ['failed', 'undelivered']))
                                <button wire:click="retryMessage({{ $message->id }})" 
                                        wire:confirm="Retry sending this message?"
                                        class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300">
                                    Retry
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No messages found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $messages->links() }}
    </div>

    {{-- Message Detail Modal --}}
    @if($selectedMessage)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                        Message Details
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Message ID</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $selectedMessage->wctp_message_id }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Carrier Message ID</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $selectedMessage->carrier_message_uid ?: 'N/A' }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($selectedMessage->status) }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Carrier</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($selectedMessage->carrier) }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">From</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $selectedMessage->from }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">To</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $selectedMessage->to }}</p>
                        </div>
                        
                        <div class="col-span-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Message</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 p-3 rounded">{{ $selectedMessage->message }}</p>
                        </div>
                        
                        @if($selectedMessage->status_details)
                            <div class="col-span-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Status Details</p>
                                <pre class="mt-1 text-xs text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-3 rounded overflow-x-auto">{{ json_encode($selectedMessage->status_details, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Created</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $selectedMessage->created_at->format('Y-m-d H:i:s') }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Submitted</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $selectedMessage->submitted_at ? $selectedMessage->submitted_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Processed</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $selectedMessage->processed_at ? $selectedMessage->processed_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Delivered/Failed</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ $selectedMessage->delivered_at ? $selectedMessage->delivered_at->format('Y-m-d H:i:s') : '' }}
                                {{ $selectedMessage->failed_at ? $selectedMessage->failed_at->format('Y-m-d H:i:s') : '' }}
                                {{ !$selectedMessage->delivered_at && !$selectedMessage->failed_at ? 'N/A' : '' }}
                            </p>
                        </div>
                    </div>

                    {{-- Modal Actions --}}
                    <div class="mt-6 flex justify-end">
                        <button wire:click="closeMessageModal" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>