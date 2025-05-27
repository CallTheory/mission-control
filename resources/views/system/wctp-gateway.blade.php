<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto">
            <x-alert-failure title="In Progress" description="This feature is currently under active development and should not be used in production"/>
        </div>
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>

            <div class="inline-flex min-w-full p-2 mx-auto mb-4">
                <livewire:system.wctp-gateway lazy />
            </div>
        </div>
    </div>

</x-app-layout>
