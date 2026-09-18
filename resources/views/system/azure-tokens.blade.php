@section('title', 'Azure Tokens')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <livewire:system.azure-tokens.overview />

        <livewire:system.azure-tokens.credentials lazy="lazy" />

        <livewire:system.azure-tokens.alerting lazy="lazy" />
    </div>

</x-app-layout>
