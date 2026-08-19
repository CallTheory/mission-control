<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="sm:rounded-lg flex">
                <div class="w-full p-8 border border-gray-300 border-double bg-white shadow mx-auto rounded-lg">

                    <div class="block mb-6">
                        Roles and their permissions are defined per team. Adjust which utilities and admin areas each role
                        can access, add your own roles, and configure agent-name rules below.
                    </div>

                    <livewire:system.role-manager />

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
