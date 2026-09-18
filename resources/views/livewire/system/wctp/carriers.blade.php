<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-surface-fg">Carriers</h2>
                <p class="mt-2 max-w-3xl text-sm text-surface-fg-soft">
                    Outbound messages go out through the carrier that owns the sending number. A number with no
                    carrier assigned uses the default, currently
                    <strong>{{ $this->defaultProvider()->label() }}</strong>.
                </p>
            </div>

            {{ $this->defaultProviderAction }}
        </div>

        <div class="space-y-4">
            @foreach($this->carriers() as $carrier)
                <div class="bg-surface-2 p-4 rounded-lg">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">
                            {{ $carrier['label'] }}
                            @if($carrier['is_default'])
                                <span class="ml-2 align-middle text-xs font-medium text-muted">default</span>
                            @endif
                        </h3>

                        @if($carrier['configured'])
                            <span class="text-sm font-medium text-success">Ready to send</span>
                        @else
                            <span class="text-sm font-medium text-danger">Not configured</span>
                        @endif
                    </div>

                    @unless($carrier['configured'])
                        <p class="mt-1 text-sm text-danger">
                            Add credentials in
                            <a href="{{ route('system.integrations') }}" class="underline">System Settings &rarr; Integrations</a>
                            before assigning numbers to this carrier.
                        </p>
                    @endunless

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-surface-fg-soft">Default From Number:</dt>
                            <dd class="font-mono text-surface-fg">{{ $carrier['from'] ?: 'Not set' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-surface-fg-soft">Inbound SMS Webhook:</dt>
                            <dd class="font-mono text-surface-fg break-all">{{ $carrier['inbound_url'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-surface-fg-soft">Delivery Receipt URL:</dt>
                            <dd class="font-mono text-surface-fg break-all">{{ $carrier['status_url'] }}</dd>
                        </div>
                    </dl>

                    @if($carrier['key'] !== 'twilio')
                        <p class="mt-2 text-xs text-muted">
                            Either URL accepts inbound messages and delivery receipts, so one entry in the
                            carrier portal is enough.
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <x-filament-actions::modals />
</div>
