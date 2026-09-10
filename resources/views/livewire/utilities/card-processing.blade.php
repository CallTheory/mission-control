<div class="w-full">
    <x-page-header title="Card Processing">
        <x-slot name="subtitle">
            Import a TBS export, review the rows, then charge them through Stripe.
        </x-slot>
        <x-slot name="actions">
            {{ $this->uploadAction }}

            @if($records)
                {{ $this->downloadAction }}
                {{ $this->clearAction }}
            @endif
        </x-slot>
    </x-page-header>

    {{ $this->table }}

    @if($records)
        <div class="mt-4 flex items-center gap-2">
            {{ $this->processAction(false) }}
            {{ $this->processAction(true) }}
        </div>
    @endif

    @if($processResults)
        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <x-alert-success
                title="{{ count($processResults['charges'] ?? []) }} charged"
                description="Payments Stripe accepted in this run." />

            <x-alert-danger
                title="{{ count($processResults['failures'] ?? []) }} failed"
                description="Rows Stripe rejected. Their reasons are on each row above." />
        </div>
    @endif

    <x-filament-actions::modals />
</div>
