<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-surface-fg leading-tight">
            {{ __('Enterprise Host Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
                @livewire('utilities.enterprise-host-management')
            </div>
        </div>
    </div>
</x-app-layout>