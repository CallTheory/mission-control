@php
    $selectStyle = 'border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ';
@endphp
<x-form-section submit="updateUserTheme">
    <x-slot name="title">
        {{ __('Theme Preferences') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Set your theme preferences. Once saved, refresh the page to see your changes.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="dashboard_timeframe" value="{{ __('Dashboard Timeframe') }}" />
            <select id="dashboard_timeframe" class=" p-2 mt-1 block w-full  {{ $selectStyle }}" wire:model.live="state.dashboard_timeframe">
               <option value="">Last 24 Hours</option>
                <option value="lastHour">Last Hour</option>
                <option value="sinceMidnight">Since Midnight</option>
            </select>
            <small class="block my-2 text-xs text-gray-500  ">The timeframe in-which dashboard statistics are displayed. Based on switch timezone.</small>
            <x-input-error for="dashboard_timeframe" class="mt-2" />

        </div>

    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
