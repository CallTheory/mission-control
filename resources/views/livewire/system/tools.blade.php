<div>
    <x-section-title>
        <x-slot name="title">System Tools</x-slot>
        <x-slot name="description">Helpful tools for system-level debug and troubleshooting.</x-slot>
    </x-section-title>

    <div class="rounded-lg shadow overflow-hidden mt-4">
        <div class="relative grid gap-6 border border-border rounded-lg shadow sm:gap-8 lg:grid-cols-2 py-4 px-4">

            <a href="/queue" target="_blank" class="-m-3 p-2 flex items-start rounded-lg bg-surface-2 hover:bg-surface-2 ">
                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary text-primary-fg sm:h-12 sm:w-12">
                    <!-- Heroicon name: outline/refresh -->
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-base font-medium text-surface-fg">Queue Viewer</p>
                    <p class="mt-1 text-sm text-muted">View the real-time queue status</p>
                </div>
            </a>

            @if(config('telescope.enabled'))
                <a href="/debug" target="_blank" class="-m-3 p-2 flex items-start rounded-lg bg-surface-2 hover:bg-surface-2 ">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary text-primary-fg sm:h-12 sm:w-12">
                        <!-- Heroicon name: outline/view-grid -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-surface-fg ">Application Debug @if(env('TELESCOPE_ENABLED') === true) {{ 'Enabled' }}@else {{ 'Disabled' }} @endif</p>
                        <p class="mt-1 text-sm text-muted">View system debug data for troubleshooting</p>
                    </div>
                </a>
            @else
                <span class="-m-3 p-2 flex items-start rounded-lg hover:bg-surface-2 bg-surface-3 cursor-not-allowed ">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-muted text-surface sm:h-12 sm:w-12">
                        <!-- Heroicon name: outline/view-grid -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-surface-fg ">Application Debug Disabled</p>
                        <p class="mt-1 text-sm text-muted">Enable application debug to view detailed diagnostic data</p>
                    </div>
                </span>
            @endif

            {{-- Queues the same job the daily schedule runs. A button rather than a link,
                 because it is an action; the ones above only navigate. --}}
            @if($this->canSyncTriggers())
                <button type="button" wire:click="syncMergeCommTriggers" wire:loading.attr="disabled"
                        class="-m-3 p-2 flex items-start text-left rounded-lg bg-surface-2 hover:bg-surface-2 cursor-pointer disabled:cursor-not-allowed">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary text-primary-fg sm:h-12 sm:w-12">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-surface-fg">
                            @if($triggerSyncQueued) Trigger Sync Queued @else Sync MergeComm Triggers @endif
                        </p>
                        <p class="mt-1 text-sm text-muted">
                            @if($triggerSyncQueued)
                                Queued. The new triggers appear once the queue has processed it.
                            @else
                                Pull web trigger definitions from Intelligent Series now, rather than waiting for the daily run
                            @endif
                        </p>
                    </div>
                </button>
            @else
                <span class="-m-3 p-2 flex items-start rounded-lg bg-surface-3 cursor-not-allowed">
                    <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-muted text-surface sm:h-12 sm:w-12">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-base font-medium text-surface-fg">Sync MergeComm Triggers</p>
                        <p class="mt-1 text-sm text-muted">Connect an Intelligent Series data source first</p>
                    </div>
                </span>
            @endif

        </div>
    </div>
    <p class="text-xs my-4 text-muted">
        <i class="font-semibold">Sync MergeComm Triggers</i> reads the web trigger definitions out of Intelligent Series
        into the catalogue that inbound email rules choose from. It runs daily on its own; this is for when a trigger has
        just been added and should be selectable straight away. Rules that route by account number do not use it.
    </p>
    <p class="text-xs my-4 text-muted">
        The <i class="font-semibold">Application Debug</i> feature causes large amounts of data to be saved into the local database, including the unencrypted details of each request. <br>
        This feature is disabled by default and can only be enabled from the server command line. It's intended usage is for short-term application debugging.
    </p>
</div>
