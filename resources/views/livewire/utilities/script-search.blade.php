<div>
    <div class="block w-full max-w-sm">
        <form wire:submit="searchScriptElements" class="bg-white border border-gray-300 shadow rounded">
            <div class="px-4 py-5 sm:p-6 ">
                <div class="block w-100">
                    <x-input value="{{ $searchQuery ?? '' }}" required name="searchQuery" id="searchQuery" type="text" class="mt-1 block w-full " wire:model.live="searchQuery" />
                    <span class="text-xs text-gray-500">Search for keywords across Intelligent Series script elements</span>
                    <x-input-error for="searchQuery" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end px-4 py-3 bg-gray-50    text-right sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">

                <span class="mr-3  text-sm" wire:loading>
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
            <div class="block bg-white rounded border border-gray-300 shadow space-y-2 w-full my-4 py-4">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="sm:flex sm:items-center">
                        <div class="sm:flex-auto">
                            <h1 class="text-base font-semibold leading-6 text-gray-900">Search Results</h1>
                            <p class="mt-2 text-sm text-gray-700">
                                Results found for your search query across all Intelligent Series scripts.
                            </p>
                        </div>
                    </div>
                    <div class="mt-8 flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client Number</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Script Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Page Name</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                    @foreach($searchResults as $result)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                                {{ $result->ClientNumber ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $result->ClientName ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ $result->ScriptName ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
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
