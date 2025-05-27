<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class="inline-flex w-full p-2 border border-gray-300 border-double bg-gray-50  shadow   mx-auto rounded-lg  mb-4">
                    <livewire:system.script-search lazy />

                </div>

            </div>
        </div>
    </div>



</x-app-layout>
