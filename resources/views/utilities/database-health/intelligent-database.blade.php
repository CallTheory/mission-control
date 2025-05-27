<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Intelligent Database</h1>
            <p class="mt-2 text-sm text-gray-700">
                Details on the individual tables within your Amtelco Intelligent Database server.
            </p>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="px-2 my-2">{{ $results->links(data:['scrollTo' => false]) }}</div>
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                    <tr>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Table</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Rows</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Reserved (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Data (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Index (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">Unused (MB)</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 font-semibold">
                                {{ $result->TableName ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ number_format($result->NumberOfRows ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($result->ReservedKB)
                                    {{ round($result->ReservedKB/1024 ?? 0, 2) }} @if($result->ReservedPercentage) <small class="text-indigo-500 align-middle">{{ round($result->ReservedPercentage, 0)  }}%</small> @endif
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($result->DataSizeKB)
                                    {{ round($result->DataSizeKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($result->IndexSizeKB)
                                    {{ round($result->IndexSizeKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($result->UnusedKB)
                                    {{ round($result->UnusedKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                        </tr>

                    @endforeach
                    </tbody>
                </table>
                <div class="px-2 my-2">{{ $results->links(data:['scrollTo' => false]) }}</div>
            </div>
        </div>
    </div>
</div>
