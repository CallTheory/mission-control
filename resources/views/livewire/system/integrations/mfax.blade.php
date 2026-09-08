<div>
    <button type="button" wire:click="mountAction('configure')"
        class="col-span-1 w-full flex justify-center py-8 px-8 bg-surface-inverse hover:bg-surface-inverse-hover cursor-pointer"
        title="Configure mFax">
        <img class="h-12 rounded-sm grayscale" src="/images/mfax.svg" alt="mFax">
    </button>

    <x-filament-actions::modals />
</div>
