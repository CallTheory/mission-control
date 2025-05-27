<div>
    <div class="flex flex-col space-y-2 py-4">

        <div class="px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-base font-semibold leading-6 text-gray-900">Script Issues</h1>
                    <p class="mt-2 text-sm text-gray-700">
                        The following scripts have problems with being decompressed.
                        Try to open and save the script in question through Intelligent Series Supervisor.
                        Scripts without a client can be System Scripts or Templates.
                    </p>
                </div>
            </div>
            @if($searchStatus === 'missing-datasource')
                <x-alert-warning title="Database Problem" description="Please setup your data source before continuing." />
            @elseif($searchStatus === 'database-error' )
                <x-alert-warning title="Database Problem" description="The query errored before completing. Please contact support." />
            @elseif($searchStatus === 'loading')
                <x-alert-info title="Loading Scripts" description="One moment while we load the requested information." />
            @elseif($searchResults && $searchStatus === 'success')
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">PageID</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client Number</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client Name</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">System Script</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Script Name</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Page Name</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach($searchResults as $result)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-400 sm:pl-0">
                                        {{ $result->PageID ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                        {{ $result->ClientNumber ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $result->ClientName ?? '' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($result->SystemScript ?? false)
                                            Yes
                                        @else
                                            No
                                        @endif
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
            @else
                <x-alert-warning title="Database Problem" description="The query errored before completing. Please contact support." />
            @endif
        </div>
    </div>
</div>
