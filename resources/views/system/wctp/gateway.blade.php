@section('title', 'WCTP Gateway')
<x-app-layout>
    <x-slot name="header">
        <x-system.wctp-header />
    </x-slot>

    <div class="p-4">
        <x-system.wctp-nav current="gateway" />

        <livewire:system.wctp.gateway />
    </div>
</x-app-layout>
