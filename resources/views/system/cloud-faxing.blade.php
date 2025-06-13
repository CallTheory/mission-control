@section('title', 'Cloud Faxing')
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
               <livewire:system.fax-notification-settings></livewire:system.fax-notification-settings>
            </div>
            <hr class="my-4 border border-gray-300">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class="text-center bg-white my-3 rounded-sm border border-gray-300 shadow py-4  w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 ">Cloud Fax Setup</h3>
                    <ul class="my-4 text-sm ">
                        <li>Configure your <a class="font-semibold hover:underline" href="https://mfax.io">mFax API Credentials</a> in the <a class="font-semibold hover:underline" href="/system/integrations">System Integrations</a> section.</li>
                        <li>Configure your <a class="font-semibold hover:underline" href="/system">fax submission failure and folder buildup email notifications</a></li>
                        <li>Enable Samba on the Mission Control server <small class="text-gray-500 ">(contact support with the IP addresses of your IS Fax Service server)</small></li>
                        <li>Setup Intelligent Series Faxing to point at the Samba shares as in the screenshot below</li>
                        <li>Create and manage your Cover Page in the <a class="font-semibold hover:underline" target="_blank" href="/system/integrations">System Integrations section</a></li>
                    </ul>
                    <img class="rounded-sm border border-gray-300  mx-auto shadow my-4" src="/images/cloud-fax-setup-example.png" alt="ISFax Setup" title="ISFax Setup Example" />
                </div>
            </div>

        </div>
    </div>



</x-app-layout>
