<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-surface-fg">Enterprise Hosts</h2>
            <p class="mt-2 max-w-3xl text-sm text-surface-fg-soft">
                Each host authenticates with its sender ID and security code, and sends from the phone numbers
                listed against it. A number goes out through the carrier it belongs to; one with no carrier
                assigned uses the default set on <a href="{{ route('system.wctp.carriers') }}" class="text-info hover:underline">Carriers</a>.
            </p>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
