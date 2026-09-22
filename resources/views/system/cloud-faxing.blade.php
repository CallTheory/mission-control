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
               <livewire:system.cloud-faxing-providers></livewire:system.cloud-faxing-providers>
            </div>
            <hr class="my-4 border border-border">
            <div class="overflow-hidden  sm:rounded-lg  flex">
               <livewire:system.fax-notification-settings></livewire:system.fax-notification-settings>
            </div>
            <hr class="my-4 border border-border">
            <div class="overflow-hidden sm:rounded-lg">
                <div class="bg-surface my-3 rounded-sm border border-border shadow p-4 w-full">
                    <h3 class="text-lg leading-6 font-medium text-surface-fg mb-1">Fax Servers</h3>
                    <p class="text-sm text-muted mb-4">
                        Where faxes arrive from. Several Intelligent Series servers can feed Mission Control;
                        only one of them processes faxes at a time, and each is read independently so one being
                        unavailable never holds up another.
                    </p>
                    <livewire:system.fax-spool-sources></livewire:system.fax-spool-sources>
                </div>
            </div>
            <hr class="my-4 border border-border">
            <div class="overflow-hidden sm:rounded-lg">
                <div class="bg-surface my-3 rounded-sm border border-border shadow p-4 w-full">
                    <h3 class="text-lg leading-6 font-medium text-surface-fg mb-1">Provider Routing</h3>
                    <p class="text-sm text-muted mb-4">
                        Which provider a fax goes out through. Set the default here rather than by re-pointing
                        Intelligent Series, and pin individual numbers or accounts when one provider's route
                        to them stops working.
                    </p>
                    <livewire:system.fax-provider-pins></livewire:system.fax-provider-pins>
                </div>
            </div>
            <hr class="my-4 border border-border">
            <div class="overflow-hidden  sm:rounded-lg  flex">

                <div class="text-center bg-surface my-3 rounded-sm border border-border shadow py-4 w-full">
                    <h3 class="text-lg leading-6 font-medium text-surface-fg ">Cloud Fax Setup</h3>
                    <ul class="my-4 text-sm ">
                        <li>Configure your <a class="font-semibold hover:underline" href="https://mfax.io">mFax API Credentials</a> in the <a class="font-semibold hover:underline" href="/system/integrations">System Integrations</a> section.</li>
                        <li>Configure your <a class="font-semibold hover:underline" href="/system">fax submission failure and folder buildup email notifications</a></li>
                        <li>Enable Samba on the Mission Control server <small class="text-muted ">(contact support with the IP addresses of your IS Fax Service server)</small></li>
                        <li>Setup Intelligent Series Faxing to point at the Samba shares as in the screenshot below</li>
                        <li>Create and manage your Cover Page in the <a class="font-semibold hover:underline" target="_blank" href="/system/integrations">System Integrations section</a></li>
                    </ul>
                    <img class="rounded-sm border border-border mx-auto shadow my-4" src="/images/cloud-fax-setup-example.png" alt="ISFax Setup" title="ISFax Setup Example" />
                </div>
            </div>

        </div>
    </div>



</x-app-layout>
