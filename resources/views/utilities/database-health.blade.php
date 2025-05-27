@section('title', 'Database Health')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>

            <div class="inline-flex min-w-full p-2 mx-auto mb-4">
                <livewire:utilities.database-health lazy />
            </div>
        </div>
    </div>

</x-app-layout>
