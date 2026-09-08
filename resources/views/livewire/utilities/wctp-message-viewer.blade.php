<div>
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

    <x-filament-actions::modals />
</div>
