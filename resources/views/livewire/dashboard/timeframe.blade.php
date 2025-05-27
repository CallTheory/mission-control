<div class="relative text-left min-w-xl pr-4">

    <select wire:change="updateDashboardTimeframe"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            id="dashboard_timeframe"
            class="p-2 mt-1 border-gray-300 rounded-md text-gray-500 w-36 text-xs shadow"
            wire:model="state.dashboard_timeframe">
            <option value="">Last 24 Hours</option>
            <option value="lastHour">Last Hour</option>
            <option value="sinceMidnight">Since Midnight</option>
    </select>

    <x-action-message class="mr-3 my-3 py-2 px-1 inline text-green-500" on="saved">
        &checkmark;
    </x-action-message>
</div>
