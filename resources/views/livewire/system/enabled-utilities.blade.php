<x-form-section submit="updateSystemFeatures">
    <x-slot name="title">
        {{ __('System Utilities') }}
    </x-slot>

    <x-slot name="description">
        Please enable/disable the utilities available to the teams in your system.
    </x-slot>

    <x-slot name="form">

        <!-- api-gateway -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.api_gateway }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="api-gateway-enabled-label">API Gateway</span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        BYOK (Bring Your Own Keys) <strong>API Gateway</strong> for multi-tenant systems
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="api-gateway-enabled-label"
                    aria-describedby="api-gateway-description"
                    @click="$wire.toggleApiGatewayUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End api-gateway -->

        <!-- better-emails -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.better_emails }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="better_emails-enabled-label">Better Emails <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="better_emails-description">
                        Send HTML-enhanced emails with <strong>Better Emails</strong> for Intelligent Series messages
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="better_emails-enabled-label"
                    aria-describedby="better_emails-description"
                    @click="$wire.toggleBetterEmailsUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End better-emails -->

        <!-- board-check -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.board_check }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="board-check-enabled-label">Board Check</span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        Message review <strong>Board Check</strong> with PeopleSoft integration for Intelligent Series messages
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="board-check-enabled-label"
                    aria-describedby="board-check-description"
                    @click="$wire.toggleBoardCheckUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End board-check -->

        <!-- call-lookup -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.call_lookup }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="call-lookup-enabled-label">Call Lookup</span>
                    <span class="text-sm text-gray-500 pr-2" id="call-lookup-description">
                        Advanced call log filter and lookup for Amtelco Genesis systems
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="call-lookup-enabled-label"
                    aria-describedby="call-lookup-description"
                    @click="$wire.toggleCallLookupUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End call-lookup -->

        <!-- card-processing -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.card_processing }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="card-processing-enabled-label">Card Processing</span>
                    <span class="text-sm text-gray-500 pr-2" id="card-processing-description">
                        Credit <strong>Card Processing</strong> with Stripe for TBS Billing
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="card-processing-enabled-label"
                    aria-describedby="card-processing-description"
                    @click="$wire.toggleCardProcessingUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End card-processing -->

        <!-- cloud-faxing -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.cloud_faxing }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="cloud-faxing-enabled-label">Cloud Faxing</span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        Copia-compatible <strong>Cloud Faxing</strong> using mFax and RingCentral
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="cloud-faxing-enabled-label"
                    aria-describedby="cloud-faxing-description"
                    @click="$wire.toggleCloudFaxingUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End cloud-faxing -->

        <!-- csv-export -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.csv_export }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="csv-export-enabled-label">CSV Export <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="csv-export-description">
                        Export CSV files based on Intelligent Series message fields.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="csv-export-enabled-label"
                    aria-describedby="csv-export-description"
                    @click="$wire.toggleCsvExportUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End csv-export -->

        <!-- database-health -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.database_health }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="database-health-enabled-label">Database Health</span>
                    <span class="text-sm text-gray-500 pr-2" id="database-health-description">
                        Review the health of your database server and Intelligent database.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="database-health-enabled-label"
                    aria-describedby="database-health-description"
                    @click="$wire.toggleDatabaseHealthUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End database-health -->

        <!-- directory-search -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.directory_search }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="directory-search-enabled-label">Directory Search</span>
                    <span class="text-sm text-gray-500 pr-2" id="directory-search-description">
                        Search globally across all Intelligent Series contact methods.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="directory-search-enabled-label"
                    aria-describedby="directory-search-description"
                    @click="$wire.toggleDirectorySearchUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End database-health -->

        <!-- inbound-email -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.inbound_email }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="inbound-email-enabled-label">Inbound Email <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        Modern <strong>Inbound Email</strong> parsing using Sendgrid
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="inbound-email-enabled-label"
                    aria-describedby="inbound-email-description"
                    @click="$wire.toggleInboundEmailUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End inbound-email -->

        <!-- voicemail-digest -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.voicemail_digest }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="voicemail-digest-enabled-label">Voicemail Digest <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="voicemail-digest-description">
                        Schedule automated emails with <strong>call recordings</strong> and transcriptions
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="voicemail-digest-enabled-label"
                    aria-describedby="voicemail-digest-description"
                    @click="$wire.toggleVoicemailDigestUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End voicemail-digest -->

        <!-- script-search -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.script_search }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="script-search-enabled-label">Script Search</span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        Find (almost) anything with global <strong>Script Search</strong> for Intelligent Series scripting
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="script-search-enabled-label"
                    aria-describedby="script-search-description"
                    @click="$wire.toggleScriptSearchUtility(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End script-search -->

    </x-slot>

    <x-slot name="actions">
        &nbsp;
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>
    </x-slot>
</x-form-section>
