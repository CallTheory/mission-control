@section('title', 'WCTP Enterprise Hosts')
<x-app-layout>
    <x-slot name="header">
        <x-system.wctp-header />
    </x-slot>

    <div class="p-4">
        <x-system.wctp-nav current="enterprise-hosts" />

        <livewire:system.wctp.enterprise-hosts />
    </div>
</x-app-layout>
