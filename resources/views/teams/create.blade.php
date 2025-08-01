<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight ">
            Create Team
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('teams.create-team-form')
        </div>
    </div>
</x-app-layout>
