<div class="relative text-left pr-4">

    <select wire:change="updateDashboardTimeframe"
            wire:model="state.dashboard_timeframe"
            id="dashboard_timeframe"
            class="p-2 mt-1 w-48 rounded-md border-border bg-surface text-surface-fg text-xs shadow
                   focus:border-primary focus:ring focus:ring-primary/30">
        <x-timeframe-options />
    </select>

    <x-action-message class="mr-3 my-3 py-2 px-1 inline text-success" on="saved">
        &checkmark;
    </x-action-message>
</div>
