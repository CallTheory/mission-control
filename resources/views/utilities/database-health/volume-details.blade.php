<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-surface-fg">Volume Details</h1>
            <p class="mt-2 text-sm text-surface-fg-soft">
                Details on your disks and volumes.
            </p>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-surface-fg sm:pl-0">Logical Name</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Drive</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Free Space</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Total Space</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Occupied Space</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-surface-fg sm:pl-0">
                                {{ $result->LogicalName ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                <code class="bg-surface-2 px-2 py-1 rounded text-surface-fg-soft">{{ $result->Drive ?? '' }}</code>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if(($result->FreeSpace / ($result->TotalSpace ?? 1)) < 0.05)
                                    <span class="px-2 py-1 bg-danger-soft rounded text-danger">{{ round($result->FreeSpace ?? 0, 2) ?? '' }} GB</span>
                                @elseif(($result->FreeSpace / ($result->TotalSpace ?? 1)) < 0.1)
                                    <span class="px-2 py-1 bg-warning-soft rounded text-warning">{{ round($result->FreeSpace ?? 0, 2) ?? '' }} GB</span>
                                @else
                                    {{ round($result->FreeSpace ?? 0, 2) ?? '' }} GB
                                @endif


                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ round($result->TotalSpace ?? 0, 2) }} GB
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm sm:pr-0 text-muted">
                                {{ round($result->OccupiedSpace ?? 0, 2)  }} GB
                            </td>

                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
