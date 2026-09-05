<div>
    <x-flash />

    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-surface-fg">WCTP Message Log</h2>
        @if($this->currentHost)
            @php $currentHost = $this->currentHost; @endphp
            @if($currentHost)
                <p class="mt-2 text-sm text-muted">
                    Showing messages for: <strong>{{ $currentHost->name }}</strong> ({{ $currentHost->senderID }})
                </p>
            @endif
        @endif
    </div>

    {{ $this->table }}

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
