<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
           Analytics <span class="0">Call Lookup</span>
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="overflow-hidden  sm:rounded-lg ">

            <div class="inline-flex mx-4 my-4">
                @include('analytics.navigation')
            </div>

            <div class="inline-flex w-full p-2 border border-double bg-gray-50  shadow   mx-auto rounded-lg  mb-4">

                <livewire:analytics.call-lookup isCallID="{{  $isCallID }}" lazy="lazy" />

            </div>

        </div>
    </div>

</x-app-layout>
