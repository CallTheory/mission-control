<x-form-section submit="updateUserTheme">
    <x-slot name="title">
        {{ __('Theme Preferences') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Set your theme preferences. Once saved, refresh the page to see your changes.') }}
    </x-slot>

    <x-slot name="form">
        <x-form-field for="user_theme" label="{{ __('Appearance') }}" error-for="state.user_theme"
            help="{{ __('Switch between light and dark mode.') }}">
            <select id="user_theme" wire:model.live="state.user_theme"
                class="p-2 mt-1 block w-full rounded-md border-border bg-surface text-surface-fg shadow focus:border-primary focus:ring focus:ring-primary/30">
                <option value="">{{ __('Light') }}</option>
                <option value="dark">{{ __('Dark') }}</option>
            </select>
        </x-form-field>

        <x-form-field for="dashboard_timeframe" label="{{ __('Dashboard Timeframe') }}" error-for="state.dashboard_timeframe"
            help="{{ __('The timeframe in-which dashboard statistics are displayed. Based on switch timezone.') }}">
            <select id="dashboard_timeframe" wire:model.live="state.dashboard_timeframe"
                class="p-2 mt-1 block w-full rounded-md border-border bg-surface text-surface-fg shadow focus:border-primary focus:ring focus:ring-primary/30">
                <option value="">{{ __('Last 24 Hours') }}</option>
                <option value="lastHour">{{ __('Last Hour') }}</option>
                <option value="sinceMidnight">{{ __('Since Midnight') }}</option>
            </select>
        </x-form-field>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
