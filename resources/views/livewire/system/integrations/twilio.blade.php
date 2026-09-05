<div>
    <button type="button" wire:click="mountAction('configure')"
        class="col-span-1 w-full flex justify-center py-8 px-8 bg-surface-inverse hover:bg-surface-inverse-hover cursor-pointer"
        title="Configure Twilio">
        <img class="h-12 rounded-sm grayscale" src="/images/twilio.svg" alt="Twilio">
    </button>

    @if($this->isConfigured())
        <p class="px-2 py-1 text-xs text-success">SMS and WCTP gateway enabled</p>
    @endif

    <x-filament-actions::modals />
</div>
