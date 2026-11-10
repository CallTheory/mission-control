<div>
    <p class="text-sm text-muted mb-3">
        Primary extension (System → Integrations → RingCentral): <span class="font-medium text-surface-fg">{{ $this->primaryState() }}</span>
    </p>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
