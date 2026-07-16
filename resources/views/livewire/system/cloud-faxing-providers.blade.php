<div class="text-left bg-white my-3 rounded-sm border border-gray-300 shadow py-4 px-6 w-full">
    <h3 class="text-lg leading-6 font-medium text-gray-900">Enabled Fax Providers</h3>
    <p class="mt-1 text-sm text-gray-500">
        Turn each cloud fax provider on or off. Disabling a provider hides it from the Cloud Faxing
        utility without deleting its saved credentials.
    </p>

    <div class="mt-4 space-y-6">

        <!-- RingCentral -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.ringcentral_enabled }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="ringcentral-enabled-label">RingCentral</span>
                    <span class="text-sm text-gray-500 pr-2" id="ringcentral-enabled-description">
                        Show the <strong>RingCentral</strong> fax status and resend view.
                        @unless($ringcentral_configured)
                            <span class="text-yellow-700">No RingCentral credentials are configured yet &mdash; add them in <a class="font-semibold hover:underline" href="/system/integrations">System Integrations</a>.</span>
                        @endunless
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="ringcentral-enabled-label"
                    aria-describedby="ringcentral-enabled-description"
                    @click="$wire.toggleRingCentral(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End RingCentral -->

        <!-- mFax -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.mfax_enabled }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="mfax-enabled-label">mFax</span>
                    <span class="text-sm text-gray-500 pr-2" id="mfax-enabled-description">
                        Show the <strong>mFax</strong> fax status and resend view.
                        @unless($mfax_configured)
                            <span class="text-yellow-700">No mFax API key is configured yet &mdash; add it in <a class="font-semibold hover:underline" href="/system/integrations">System Integrations</a>.</span>
                        @endunless
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="mfax-enabled-label"
                    aria-describedby="mfax-enabled-description"
                    @click="$wire.toggleMfax(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End mFax -->

    </div>
</div>
