<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-surface-fg">Intelligent Database</h1>
            <p class="mt-2 text-sm text-surface-fg-soft">
                Details on the individual tables within your Amtelco Intelligent Database server.
            </p>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="px-2 my-2">{{ $results->links(data:['scrollTo' => false]) }}</div>
                <table class="min-w-full divide-y divide-border">
                    <thead>
                    <tr>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Table</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Rows</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Reserved (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Data (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Index (MB)</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg whitespace-nowrap">Unused (MB)</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted font-semibold">
                                {{ $result->TableName ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ number_format($result->NumberOfRows ?? 0) }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if($result->ReservedKB)
                                    {{ round($result->ReservedKB/1024 ?? 0, 2) }} @if($result->ReservedPercentage) <small class="text-primary align-middle">{{ round($result->ReservedPercentage, 0)  }}%</small> @endif
                                @else
                                    <span class="text-subtle">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if($result->DataSizeKB)
                                    {{ round($result->DataSizeKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-subtle">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if($result->IndexSizeKB)
                                    {{ round($result->IndexSizeKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-subtle">&mdash;</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if($result->UnusedKB)
                                    {{ round($result->UnusedKB/1024 ?? 0, 2) }}
                                @else
                                    <span class="text-subtle">&mdash;</span>
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
