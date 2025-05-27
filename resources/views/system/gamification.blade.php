@php
use Illuminate\Support\Facades\Auth;
@endphp
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class="inline-flex w-full p-12 border  border-double border-indigo-800 shadow bg-indigo-900 mx-auto rounded-lg">

                </div>

            </div>
        </div>
    </div>



</x-app-layout>
