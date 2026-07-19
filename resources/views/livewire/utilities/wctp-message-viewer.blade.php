<div>
    <x-flash />

    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-surface-fg">WCTP Message Log</h2>
        @if($host)
            @php $currentHost = $hosts->find($host); @endphp
            @if($currentHost)
                <p class="mt-2 text-sm text-muted">
                    Showing messages for: <strong>{{ $currentHost->name }}</strong> ({{ $currentHost->senderID }})
                </p>
            @endif
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <x-search-input wire-model="search" placeholder="Phone, message, or ID..." />

        <x-filter-select for="host" label="Enterprise Host" wire-model="host" :placeholder="null">
            <option value="">All Hosts</option>
            @foreach($hosts as $hostOption)
                <option value="{{ $hostOption->id }}">{{ $hostOption->name }} ({{ $hostOption->senderID }})</option>
            @endforeach
        </x-filter-select>

        <x-filter-select for="filterStatus" label="Status" wire-model="filterStatus" placeholder="All Statuses">
            <option value="pending">Pending</option>
            <option value="queued">Queued</option>
            <option value="submitted">Submitted</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
            <option value="undelivered">Undelivered</option>
        </x-filter-select>

        <x-filter-select for="filterDirection" label="Direction" wire-model="filterDirection" placeholder="All Directions">
            <option value="outbound">Outbound</option>
            <option value="inbound">Inbound</option>
        </x-filter-select>

        <x-filter-select for="filterCarrier" label="Carrier" wire-model="filterCarrier" :placeholder="null">
            <option value="">All Carriers</option>
            @foreach($carriers as $carrier)
                <option value="{{ $carrier }}">{{ ucfirst($carrier) }}</option>
            @endforeach
        </x-filter-select>
    </div>

    {{-- Date Range Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-form-field for="dateFrom" label="From Date" type="date" wire:model.live="dateFrom" col-span="" />
        <x-form-field for="dateTo" label="To Date" type="date" wire:model.live="dateTo" col-span="" />
        <div class="flex items-end">
            <x-button wire:click="exportMessages" class="bg-success hover:bg-success">Export CSV</x-button>
        </div>
    </div>

    {{-- Messages Table --}}
    <x-table>
        <x-slot name="head">
            <x-table.heading>Time</x-table.heading>
            <x-table.heading>Host</x-table.heading>
            <x-table.heading>Direction</x-table.heading>
            <x-table.heading>From/To</x-table.heading>
            <x-table.heading>Message</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Actions</x-table.heading>
        </x-slot>

        @forelse($messages as $message)
            <x-table.row>
                <x-table.cell>
                    {{ $message->created_at->format('Y-m-d H:i:s') }}
                    <div class="text-xs text-muted">{{ $message->created_at->diffForHumans() }}</div>
                </x-table.cell>
                <x-table.cell>
                    @if($message->enterpriseHost)
                        <div class="text-sm font-medium">{{ $message->enterpriseHost->name }}</div>
                        <div class="text-xs text-muted">{{ $message->enterpriseHost->senderID }}</div>
                    @else
                        <span class="text-muted">Unknown</span>
                    @endif
                </x-table.cell>
                <x-table.cell>
                    <x-badge :color="$message->direction === 'outbound' ? 'blue' : 'purple'">{{ ucfirst($message->direction) }}</x-badge>
                </x-table.cell>
                <x-table.cell>
                    <div><strong>To:</strong> {{ $message->to }}</div>
                    <div><strong>From:</strong> {{ $message->from }}</div>
                </x-table.cell>
                <x-table.cell :nowrap="false">
                    <div class="max-w-xs truncate">{{ Str::limit($message->message, 50) }}</div>
                    <div class="text-xs text-muted mt-1">ID: {{ $message->wctp_message_id }}</div>
                    @if($message->reply_with)
                        <x-badge color="yellow" class="mt-1">Reply: {{ $message->reply_with }}</x-badge>
                    @endif
                </x-table.cell>
                <x-table.cell>
                    <x-status-badge :status="$message->status" :map="['undelivered' => 'red', 'submitted' => 'yellow']" />
                    @if($message->retry_count > 0)
                        <div class="text-xs text-muted mt-1">Retries: {{ $message->retry_count }}</div>
                    @endif
                </x-table.cell>
                <x-table.cell>
                    <button wire:click="viewMessage({{ $message->id }})" class="text-primary hover:underline mr-3">View</button>
                    @if(in_array($message->status, ['failed', 'undelivered']))
                        <button wire:click="retryMessage({{ $message->id }})" wire:confirm="Retry sending this message?"
                                class="text-warning hover:underline">Retry</button>
                    @endif
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7">No messages found.</x-table.empty>
        @endforelse

        <x-slot name="footer">{{ $messages->links() }}</x-slot>
    </x-table>

    {{-- Message Detail Modal --}}
    @if($selectedMessage)
        <x-dialog-modal wire:model.live="selectedMessage" maxWidth="2xl">
            <x-slot name="title">Message Details</x-slot>

            <x-slot name="content">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-muted">Message ID</p>
                        <p class="mt-1 text-sm text-surface-fg font-mono">{{ $selectedMessage->wctp_message_id }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Carrier Message ID</p>
                        <p class="mt-1 text-sm text-surface-fg font-mono">{{ $selectedMessage->carrier_message_uid ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Status</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ ucfirst($selectedMessage->status) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Carrier</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ ucfirst($selectedMessage->carrier ?? '') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">From</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ $selectedMessage->from }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">To</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ $selectedMessage->to }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm font-medium text-muted">Message</p>
                        <p class="mt-1 text-sm text-surface-fg whitespace-pre-wrap bg-surface-2 p-3 rounded">{{ $selectedMessage->message }}</p>
                    </div>
                    @if($selectedMessage->status_details)
                        <div class="col-span-2">
                            <p class="text-sm font-medium text-muted">Status Details</p>
                            <pre class="mt-1 text-xs text-surface-fg bg-surface-2 p-3 rounded overflow-x-auto">{{ json_encode($selectedMessage->status_details, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-muted">Created</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ $selectedMessage->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Submitted</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ $selectedMessage->submitted_at ? $selectedMessage->submitted_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Processed</p>
                        <p class="mt-1 text-sm text-surface-fg">{{ $selectedMessage->processed_at ? $selectedMessage->processed_at->format('Y-m-d H:i:s') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted">Delivered/Failed</p>
                        <p class="mt-1 text-sm text-surface-fg">
                            {{ $selectedMessage->delivered_at ? $selectedMessage->delivered_at->format('Y-m-d H:i:s') : '' }}
                            {{ $selectedMessage->failed_at ? $selectedMessage->failed_at->format('Y-m-d H:i:s') : '' }}
                            {{ !$selectedMessage->delivered_at && !$selectedMessage->failed_at ? 'N/A' : '' }}
                        </p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeMessageModal">Close</x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
