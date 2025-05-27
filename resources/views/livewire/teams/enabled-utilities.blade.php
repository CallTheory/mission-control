@php
use App\Models\Stats\Helpers;
@endphp
<x-form-section submit="updateTeamUtilities">
    <x-slot name="title">
        {{ __('Team Utilities') }}
    </x-slot>

    <x-slot name="description">
        Enable utilities available to your team.
    </x-slot>

    <x-slot name="form">

        @if(Helpers::isSystemFeatureEnabled('api-gateway'))
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
                    @click="$wire.toggleSetting('api_gateway'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('better-emails'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="better_emails-enabled-label"
                    aria-describedby="better_emails-description"
                    @click="$wire.toggleSetting('better_emails'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('board-check'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="board-check-enabled-label"
                    aria-describedby="board-check-description"
                    @click="$wire.toggleSetting('board_check'); isEnabled = !isEnabled"
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
        @endif


        @if(Helpers::isSystemFeatureEnabled('call-lookup'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="call-lookup-enabled-label"
                    aria-describedby="call-lookup-description"
                    @click="$wire.toggleSetting('call_lookup'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('card-processing'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="card-processing-enabled-label"
                    aria-describedby="card-processing-description"
                    @click="$wire.toggleSetting('card_processing'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('cloud-faxing'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="cloud-faxing-enabled-label"
                    aria-describedby="cloud-faxing-description"
                    @click="$wire.toggleSetting('cloud_faxing'); isEnabled = !isEnabled"
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
       @endif

        @if(Helpers::isSystemFeatureEnabled('csv-export'))
            <!-- csv-export -->
            <div class="col-span-6 sm:col-span-4">
                <div x-data="{ isEnabled: $wire.csv_export }" class="flex items-center justify-between">
            <span class="flex flex-grow flex-col">
                <span class="text-md font-semibold leading-6 text-gray-900" id="csv-export-enabled-label">CSV Export</span>
                <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                    Export CSV files based on Intelligent Series message fields.
                </span>
            </span>
                    <button
                        type="button"
                        :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="isEnabled.toString()"
                        aria-labelledby="csv-export-enabled-label"
                        aria-describedby="csv-export-description"
                        @click="$wire.toggleSetting('csv_export'); isEnabled = !isEnabled"
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
        @endif


        @if(Helpers::isSystemFeatureEnabled('database-health'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="database-health-enabled-label"
                    aria-describedby="database-health-description"
                    @click="$wire.toggleSetting('database_health'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('directory-search'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="directory-search-enabled-label"
                    aria-describedby="directory-search-description"
                    @click="$wire.toggleSetting('directory_search'); isEnabled = !isEnabled"
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
        @endif


        @if(Helpers::isSystemFeatureEnabled('inbound-email'))
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
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="inbound-email-enabled-label"
                    aria-describedby="inbound-email-description"
                    @click="$wire.toggleSetting('inbound_email'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('mcp-server'))
            <!-- mcp-server -->
            <div class="col-span-6 sm:col-span-4">
                <div x-data="{ isEnabled: $wire.mcp_server }" class="flex items-center justify-between">
            <span class="flex flex-grow flex-col">
                <span class="text-md font-semibold leading-6 text-gray-900" id="mcp-server-enabled-label">MCP Server <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                    A simple <strong>Model Context Protocol (MCP) Server</strong> for your AI stack.
                </span>
            </span>
                    <button
                        type="button"
                        :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="isEnabled.toString()"
                        aria-labelledby="mcp-server-enabled-label"
                        aria-describedby="mcp-server-description"
                        @click="$wire.toggleSetting('mcp_server'); isEnabled = !isEnabled"
                    >
                <span
                    aria-hidden="true"
                    :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                ></span>
                    </button>
                </div>
            </div>
            <!-- End mcp-server -->
        @endif

        @if(Helpers::isSystemFeatureEnabled('script-search'))
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
                    @click="$wire.toggleSetting('script_search'); isEnabled = !isEnabled"
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
        @endif

        @if(Helpers::isSystemFeatureEnabled('wctp-gateway'))
        <!-- wctp-gateway -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.wctp_gateway }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="wctp-gateway-enabled-label">WCTP Gateway <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="wctp-gateway-description">
                        Send and receive SMS through our <strong>WCTP Gateway</strong> and 3rd-party telecom APIs
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="wctp-gateway-enabled-label"
                    aria-describedby="wctp-gateway-description"
                    @click="$wire.toggleSetting('wctp_gateway'); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End wctp-gateway -->
        @endif

    </x-slot>

    <x-slot name="actions">
        &nbsp;
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>
    </x-slot>
</x-form-section>
