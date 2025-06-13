@php
    use Illuminate\Support\Str;
    use App\Models\Stats\Helpers;
@endphp
<div x-data="{ open: false }" @click.away="open = false" class="relative inline-block text-left z-100">

    <button
            @click="open = !open"
            type="button"
            class="cursor-pointer font-semibold inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white p-2 text-xs text-gray-500 shadow ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            id="menu-button"
            aria-expanded="true"
            aria-haspopup="true">
            @if(basename(request()->path()) === 'utilities')
                Overview
            @elseif(Str::startsWith(basename(request()->path()), 'board-'))
                Board Check
            @elseif(Str::startsWith(request()->path(), 'utilities/call-lookup'))
                Call Lookup
            @else
                {{ ucwords(implode(' ', explode('-', basename(request()->path())))) }}
            @endif

        <svg class="-mr-1 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute right-0 mt-2 w-56 origin-top-right rounded-md bg-white shadow text-xs border border-gray-300 focus:outline-hidden"
         role="menu"
         aria-orientation="vertical"
         aria-labelledby="menu-button"
         tabindex="-1">
        <div class="py-1" role="none">

            <a href="/utilities/" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-0">Overview</a>

            @if(Helpers::isSystemFeatureEnabled('api-gateway') && request()->user()->currentTeam->utility_api_gateway)
                <a href="/utilities/api-gateway" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-222">API Gateway</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('better-emails') && request()->user()->currentTeam->utility_better_emails)
                <a href="/utilities/better-emails" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2345">Better Emails</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('board-check') && request()->user()->currentTeam->utility_board_check)
                <a href="/utilities/board-check" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2">Board Check</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('call-lookup') && request()->user()->currentTeam->utility_call_lookup)
                <a href="/utilities/call-lookup" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-1">Call Lookup</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('card-processing') && request()->user()->currentTeam->utility_card_processing)
                <a href="/utilities/card-processing" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-20">Card Processing</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('cloud-faxing') && request()->user()->currentTeam->utility_cloud_faxing)
                <a href="/utilities/cloud-faxing" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-21">Cloud Faxing</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('csv-export') && request()->user()->currentTeam->utility_csv_export)
                <a href="/utilities/csv-export" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-21">CSV Export</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('database-health') && request()->user()->currentTeam->utility_database_health)
                <a href="/utilities/database-health" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-22">Database Health</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('directory-search') && request()->user()->currentTeam->utility_directory_search)
                <a href="/utilities/directory-search" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2332">Directory Search</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('inbound-email') && request()->user()->currentTeam->utility_inbound_email)
                <a href="/utilities/inbound-email" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-23">Inbound Email</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('mcp-server') && request()->user()->currentTeam->utility_mcp_server)
                <a href="/utilities/mcp-server" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-243">MCP Server</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('script-search') && request()->user()->currentTeam->utility_script_search)
                <a href="/utilities/script-search" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-26">Script Search</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('wctp-gateway') && request()->user()->currentTeam->utility_wctp_gateway)
                <a href="/utilities/wctp-gateway" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-263">WCTP Gateway</a>
            @endif

        </div>
    </div>
</div>
