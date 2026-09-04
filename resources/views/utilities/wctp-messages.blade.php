<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-surface-fg leading-tight">
            {{ __('WCTP Message Log') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-surface overflow-hidden shadow-xl sm:rounded-lg p-6">
                @livewire('utilities.wctp-message-viewer')
            </div>
        </div>
    </div>
</x-app-layout>