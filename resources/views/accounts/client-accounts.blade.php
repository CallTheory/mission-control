<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/accounts">Intelligent Series Accounts</a>
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>

            <div class="inline-flex min-w-full p-2 mx-auto mb-4">
                <livewire:accounts.clients lazy="lazy" />
            </div>
        </div>
    </div>


</x-app-layout>
