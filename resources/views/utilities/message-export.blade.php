@section('title', 'Message Export')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded-sm border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>

            <div class="block min-w-full px-2 mx-auto">
                @include('utilities.message-export-nav')

                <div class="mt-4 p-2">
                    <livewire:utilities.message-export lazy="lazy" />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
