<div>
    <div class="flex">
        <form wire:submit="searchScriptElements" class="bg-surface border border-border shadow rounded">
            <div class="px-4 py-5 sm:p-6 ">
                <div class="block w-100">
                    <x-input value="{{ $searchQuery ?? '' }}" required name="searchQuery" id="searchQuery" type="text" class="mt-1 block w-full " wire:model.live="searchQuery" />
                    <span class="text-xs text-muted">Search for keywords across Intelligent Series script elements</span>
                    <x-input-error for="searchQuery" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end px-4 py-3 bg-surface-3 text-right sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">

                <span class="mr-3 text-sm" wire:loading>
                    {{ __('Searching...') }}
                </span>

                <x-action-message class="mr-3 " on="search">
                    {{ __('Search complete.') }}
                </x-action-message>

                <x-button class="print:hidden">
                    {{ __('Search') }}
                </x-button>
            </div>
        </form>
    </div>
    @if($searchResults !== null)
        <div class="block w-full min-w-full">
            <div class="block bg-surface rounded border border-border shadow space-y-2 w-full my-4 py-4">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="sm:flex sm:items-center">
                        <div class="sm:flex-auto">
                            <h1 class="text-base font-semibold leading-6 text-surface-fg">Search Results</h1>
                            <p class="mt-2 text-sm text-surface-fg-soft">
                                Results found for your search query across all Intelligent Series scripts.
                            </p>
                        </div>
                    </div>
                    <div class="mt-8 flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <table class="min-w-full divide-y divide-border">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Client Number</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Client Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Script Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Page Name</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-soft">
                                    @foreach($searchResults as $result)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-surface-fg sm:pl-0">
                                                {{ $result->ClientNumber ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {{ $result->ClientName ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {{ $result->ScriptName ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {{ $result->PageName ?? '' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
