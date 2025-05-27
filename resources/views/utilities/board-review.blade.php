@php

use Illuminate\Support\Facades\Auth;
use App\Models\MergeCommISWebTrigger;
@endphp
@section('title', 'Board Review')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation /> <span class="text-gray-400 0">Board Review</span>
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>
            <div class="inline-flex min-w-full p-2 mx-auto mb-4">
                <livewire:utilities.board-review lazy></livewire:utilities.board-review>
            </div>
        </div>
    </div>

</x-app-layout>
