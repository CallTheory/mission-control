@php

use Illuminate\Support\Facades\Auth;
use App\Models\MergeCommISWebTrigger;
@endphp
@section('title', 'Cloud Faxing')

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex ">
            <div class="w-full">
                @include('utilities.cloud-faxing-nav')

                <div>
                    <livewire:utilities.cloud-faxing lazy="lazy"></livewire:utilities.cloud-faxing>
                </div>

            </div>

        </div>
    </div>

</x-app-layout>
