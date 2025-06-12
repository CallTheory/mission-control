@section('title', 'Script Search')
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a> <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 w-full">

          <livewire:utilities.script-search lazy="lazy" />
        </div>

    </div>

</x-app-layout>
