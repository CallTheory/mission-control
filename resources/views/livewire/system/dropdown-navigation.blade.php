@php
    use App\Models\Stats\Helpers;
    use Illuminate\Support\Str;
@endphp
<div x-data="{ open: false }" @click.away="open = false" class="relative inline-block text-left z-100 font-semibold">

    <button
        @click="open = !open"
        type="button"
        class="cursor-pointer inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white p-2 text-xs text-gray-500 shadow ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
        id="menu-button"
        aria-expanded="true"
        aria-haspopup="true">
        @if(basename(request()->path()) === 'system')
            Overview
        @elseif(Str::startsWith(request()->path(), 'system/users'))
            Users & Teams
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
         class="absolute right-0 mt-2 w-56 origin-top-right rounded-md bg-white shadow border border-gray-300 text-xs focus:outline-hidden"
         role="menu"
         aria-orientation="vertical"
         aria-labelledby="menu-button"
         tabindex="-1">
        <div class="py-1" role="none">

            <a class="text-gray-400 font-normal block px-4 py-2 text-xs border-b border-gray-300">
                System-Wide Settings
            </a>

            <a href="/system/" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-0">General</a>

            <a href="/system/data-sources" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-1">Data Sources</a>

            <a href="/system/integrations" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2">Integrations</a>

            <a href="/system/users" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-3">Users & Teams</a>

            <a href="/system/saml-settings" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-4">SAML Settings</a>

            <a href="/system/permissions" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-5">Permissions</a>

            <a class="text-gray-400 font-normal block px-4 py-2 text-xs border-b border-t border-gray-300 mt-4">
                Utility-Specific Settings
            </a>

            @if(Helpers::isSystemFeatureEnabled('api-gateway'))
                <a href="/system/api-gateway" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-6">API Gateway</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('better-emails'))
                <a href="/system/better-emails" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-7">Better Emails</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('board-check'))
                <a href="/system/board-check" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-8">Board Check</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('cloud-faxing'))
                <a href="/system/cloud-faxing" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-9">Cloud Faxing</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('csv-export'))
                <a href="/system/csv-export" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-10">CSV Export</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('mcp-server'))
                <a href="/system/mcp-server" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-11">MCP Server</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('script-search'))
                <a href="/system/script-search" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-12">Script Search</a>
            @endif

            @if(Helpers::isSystemFeatureEnabled('wctp-gateway'))
                <a href="/system/wctp-gateway" class="hover:text-gray-900 hover:bg-gray-100 text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-13">WCTP Gateway</a>
            @endif

        </div>
    </div>
</div>
