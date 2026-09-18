<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
        {{-- Header --}}
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-surface-fg">WCTP Message Log</h2>
            @if($this->currentHost)
                @php $currentHost = $this->currentHost; @endphp
                <p class="mt-2 text-sm text-muted">
                    Showing messages for: <strong>{{ $currentHost->name }}</strong> ({{ $currentHost->senderID }})
                    &middot; <a href="{{ route('system.wctp.messages') }}" class="text-info hover:underline">show all</a>
                </p>
            @endif
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
