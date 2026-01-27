@php
use App\Models\Stats\Helpers;
$svgClasses = 'class="w-16 h-16 mx-auto text-gray-400  group-hover:text-white transition transform duration-700 ease-in-out"';
@endphp
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline-block font-normal text-xl leading-tight ">
            Utilities <livewire:utilities.dropdown-navigation />
        </h2>
    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto z-0 rounded-sm border border-gray-300 shadow bg-white ">

            <div class="overflow-hidden  sm:rounded-lg ">
                <div class="p-2  w-full  mx-auto">
                    @include('layouts.width-toggle')
                </div>

                <div class="px-6 py-12 w-full mx-auto">

                    <h3 class="block mb-4">
                        Utilities are augmented and/or additional features for the Amtelco ecosystem.
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5  gap-x-4 gap-y-4">

                        @if(Helpers::isSystemFeatureEnabled('api-gateway') && request()->user()->currentTeam->utility_api_gateway)
                            <a class="group" title="API Gateway" href="/utilities/api-gateway">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" {!! $svgClasses !!}>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">API Gateway</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('better-emails') && request()->user()->currentTeam->utility_better_emails)
                            <a class="group" title="Board Check" href="/utilities/better-emails">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" {!! $svgClasses !!}>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Better Emails</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('board-check') && request()->user()->currentTeam->utility_board_check)
                            <a class="group" title="Board Check" href="/utilities/board-check">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Board Check</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('call-lookup') && request()->user()->currentTeam->utility_call_lookup)
                        <a class="group" title="Call Lookup" href="/utilities/call-lookup">
                            <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                <svg class="w-16 h-16 mx-auto text-gray-400  group-hover:text-white
                                transition transform duration-700 ease-in-out" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Call Lookup</small>
                            </div>
                        </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('card-processing') && request()->user()->currentTeam->utility_card_processing)
                            <a class="group" title="Card Processing" href="/utilities/card-processing">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Card Processing</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('config-editor') && request()->user()->currentTeam->utility_config_editor)
                            <a class="group" title="Config Editor" href="/utilities/config-editor">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Config Editor</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('cloud-faxing') && request()->user()->currentTeam->utility_cloud_faxing)
                            <a class="group" title="Cloud Faxing" href="/utilities/cloud-faxing">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Cloud Faxing</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('csv-export') && request()->user()->currentTeam->utility_csv_export)
                            <a class="group" title="CSV Export" href="/utilities/csv-export">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" {!! $svgClasses !!}>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">CSV Export</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('database-health') && request()->user()->currentTeam->utility_database_health)
                        <a class="group" title="Database Health" href="/utilities/database-health">
                            <div class="bg-white px-4 py-8  transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Database Health</small>
                            </div>
                        </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('directory-search') && request()->user()->currentTeam->utility_directory_search)
                            <a class="group" title="Directory Search" href="/utilities/directory-search">
                                <div class="bg-white px-4 py-8  transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Directory Search</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('inbound-email') && request()->user()->currentTeam->utility_inbound_email)
                            <a class="group" title="Inbound Email" href="/utilities/inbound-email">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"></path></svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Inbound Email</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('mcp-server') && request()->user()->currentTeam->utility_mcp_server)
                            <a class="group" title="MCP Server" href="/utilities/mcp-server">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">MCP Server</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('voicemail-digest') && request()->user()->currentTeam->utility_voicemail_digest)
                            <a class="group" title="Voicemail Digest" href="/utilities/voicemail-digest">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Voicemail Digest</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('script-search') && request()->user()->currentTeam->utility_script_search)
                            <a class="group" title="Script Search" href="/utilities/script-search">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg {!! $svgClasses !!} fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"></path></svg>
                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">Script Search</small>
                                </div>
                            </a>
                        @endif

                        @if(Helpers::isSystemFeatureEnabled('wctp-gateway') && request()->user()->currentTeam->utility_wctp_gateway)
                            <a class="group" title="WCTP Gateway" href="/utilities/wctp-gateway">
                                <div class="bg-white px-4 py-8 transition transform duration-700 ease-in-out group-hover:bg-gray-700 rounded-sm shadow border border-gray-300 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" {!! $svgClasses !!}>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                    </svg>

                                    <small class="mx-auto text-sm group-hover:text-white transform transition duration-700 ease-in-out">WCTP Gateway</small>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
