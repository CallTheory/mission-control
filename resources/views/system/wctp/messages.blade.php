@section('title', 'WCTP Messages')
<x-app-layout>
    <x-slot name="header">
        <x-system.wctp-header />
    </x-slot>

    <div class="p-4">
        <x-system.wctp-nav current="messages" />

        <livewire:system.wctp.messages />
    </div>
</x-app-layout>
