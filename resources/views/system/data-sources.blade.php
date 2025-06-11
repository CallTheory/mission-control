<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class=" w-full p-12 border  border-double bg-gray-100  shadow   mx-auto rounded-lg">

                    <div class="mt-10 sm:mt-0">
                        <livewire:system.data-sources.intelligent lazy="lazy" />
                    </div>
                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <livewire:system.data-sources.client-db lazy="lazy" />
                    </div>
                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <livewire:system.data-sources.is-web-api lazy="lazy" />
                    </div>


                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <livewire:system.data-sources.is-user lazy="lazy" />
                    </div>

                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <livewire:system.data-sources.amtelco-s-m-t-p lazy="lazy" />
                    </div>

                </div>

            </div>
        </div>
    </div>



</x-app-layout>
