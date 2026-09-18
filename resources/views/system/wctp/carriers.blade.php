@section('title', 'WCTP Carriers')
<x-app-layout>
    <x-slot name="header">
        <x-system.wctp-header />
    </x-slot>

    <div class="p-4">
        <x-system.wctp-nav current="carriers" />

        <livewire:system.wctp.carriers />
    </div>
</x-app-layout>
