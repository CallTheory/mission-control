<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class="inline-flex w-full p-12 border border-gray-300 border-double bg-white  shadow  mx-auto rounded-lg">

                    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8">
                        <div class="lg:grid lg:grid-cols-2 lg:gap-8 lg:items-center">
                            <div>
                                <h2 class="text-3xl font-extrabold text-gray-900  sm:text-4xl">
                                   System API Integrations
                                </h2>
                                <p class="mt-3 max-w-3xl text-lg text-gray-500 ">
                                    Used in first-party integrations built directly into the Mission Control application.
                                </p>
                            </div>
                            <div class="mt-8 grid grid-cols-2 gap-0.5 md:grid-cols-3 lg:mt-0 lg:grid-cols-2">
                                <livewire:system.integrations.sendgrid lazy></livewire:system.integrations.sendgrid>
                                <livewire:system.integrations.stripe lazy></livewire:system.integrations.stripe>
                                <livewire:system.integrations.mfax lazy></livewire:system.integrations.mfax>
                                <livewire:system.integrations.ringcentral lazy></livewire:system.integrations.ringcentral>
                                <livewire:system.integrations.bandwidth lazy></livewire:system.integrations.bandwidth>
                                <livewire:system.integrations.twilio lazy></livewire:system.integrations.twilio>
                                <livewire:system.integrations.commio lazy></livewire:system.integrations.commio>
                                <livewire:system.integrations.people-praise lazy></livewire:system.integrations.people-praise>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</x-app-layout>
