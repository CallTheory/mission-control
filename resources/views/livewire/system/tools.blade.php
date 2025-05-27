<div>
    <x-section-title>
        <x-slot name="title">System Tools</x-slot>
        <x-slot name="description">Helpful tools for system-level debug and troubleshooting.</x-slot>
    </x-section-title>

    <div class="rounded-lg shadow overflow-hidden mt-4">
        <div class="relative grid gap-6 border border-gray-300 rounded-lg  shadow  sm:gap-8 lg:grid-cols-2 py-4 px-4">

            <a href="/queue" target="_blank" class="-m-3 p-2 flex items-start rounded-lg bg-gray-50 hover:bg-gray-100 ">
                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-indigo-500 text-white sm:h-12 sm:w-12">
                    <!-- Heroicon name: outline/refresh -->
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-base font-medium  text-gray-900">Queue Viewer</p>
                    <p class="mt-1 text-sm text-gray-500 0">View the real-time queue status</p>
                </div>
            </a>

            @if(config('telescope.enabled'))
                <a href="/debug" target="_blank" class="-m-3 p-2 flex items-start rounded-lg bg-gray-50 hover:bg-gray-100 ">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-indigo-500 text-white sm:h-12 sm:w-12">
                        <!-- Heroicon name: outline/view-grid -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-gray-900 ">Application Debug @if(env('TELESCOPE_ENABLED') === true) {{ 'Enabled' }}@else {{ 'Disabled' }} @endif</p>
                        <p class="mt-1 text-sm text-gray-500 0">View system debug data for troubleshooting</p>
                    </div>
                </a>
            @else
                <span class="-m-3 p-2 flex items-start rounded-lg hover:bg-gray-100 bg-gray-200  cursor-not-allowed ">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-gray-500 text-white sm:h-12 sm:w-12">
                        <!-- Heroicon name: outline/view-grid -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-gray-900 ">Application Debug Disabled</p>
                        <p class="mt-1 text-sm text-gray-500 0">Enable application debug to view detailed diagnostic data</p>
                    </div>
                </span>
            @endif

        </div>
    </div>
    <p class="text-xs my-4 text-gray-400">
        The <i class="font-semibold">Application Debug</i> feature causes large amounts of data to be saved into the local database, including the unencrypted details of each request. <br>
        This feature is disabled by default and can only be enabled from the server command line. It's intended usage is for short-term application debugging.
    </p>
</div>
