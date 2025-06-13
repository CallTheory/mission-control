@section('title', 'Board Check')
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

                <div class="inline-flex w-full p-2 mx-auto   mb-4">

                    <livewire:system.board-check lazy />
                </div>

            </div>
        </div>
    </div>



</x-app-layout>
