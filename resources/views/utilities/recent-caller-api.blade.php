@section('title', 'Recent Caller API')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex ">
            <div>
                <livewire:utilities.recent-caller-api />
            </div>

        </div>
    </div>

</x-app-layout>
