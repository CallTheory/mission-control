<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/accounts/">Intelligent Series Account</a> <a class="border border-border px-2 py-0.5 rounded bg-surface-2 text-primary hover:underline" href="/accounts/{{ $client_number }}">{{ $client_number }}</a>
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-surface shadow border-border">
            <div class="m-4">
                @include('layouts.width-toggle')
            </div>
            <div class="block min-w-full p-2 mx-auto mb-4">
                <livewire:accounts.client :client_number="$client_number"></livewire:accounts.client>
            </div>
        </div>
    </div>


</x-app-layout>
