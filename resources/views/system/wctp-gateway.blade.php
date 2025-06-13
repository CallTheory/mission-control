@section('title', 'WCTP Gateway')
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>

            <div class="w-full min-w-full p-4 mx-auto mb-4">
                <livewire:system.wctp-gateway lazy="lazy" />
            </div>
        </div>
    </div>

</x-app-layout>
