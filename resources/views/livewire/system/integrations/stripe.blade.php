<div>
    <button type="button" wire:click="mountAction('configure')"
        class="col-span-1 w-full flex justify-center py-8 px-8 bg-surface-inverse hover:bg-surface-inverse-hover cursor-pointer"
        title="Configure Stripe">
        <img class="h-12 rounded-sm logo-mark" src="/images/stripe.svg" alt="Stripe">
    </button>

    <x-filament-actions::modals />
</div>
