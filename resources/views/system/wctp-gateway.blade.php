@section('title', 'WCTP Gateway')
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent" class="max-w-7xl mx-auto">
            <div class="mb-4">
                @include('layouts.width-toggle')
            </div>
            <livewire:system.wctp-gateway lazy="lazy" />
        </div>
    </div>

</x-app-layout>
