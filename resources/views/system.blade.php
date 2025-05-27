<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg flex">
                <div>

                    <div class="rounded-lg w-full p-12 border border-gray-300 bg-white  shadow mx-auto rounded-md">

                        <livewire:system.tools></livewire:system.tools>

                        <x-section-border />

                        <livewire:system.timezone></livewire:system.timezone>

                        <x-section-border />

                        <livewire:system.enabled-features></livewire:system.enabled-features>

                        <x-section-border />

                        <livewire:system.enabled-utilities></livewire:system.enabled-utilities>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-app-layout>
