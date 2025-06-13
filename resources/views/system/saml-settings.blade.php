@section('title', 'SAML Settings')
<x-app-layout>
    <x-slot name="header">
        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>
    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden  sm:rounded-lg  flex">
                <div class=" w-full p-12   shadow   mx-auto rounded-lg">
                    <div class="mt-10 sm:mt-0">
                        <livewire:system.saml-settings lazy="lazy" />
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
