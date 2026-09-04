<div class="px-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-surface-fg">Database Server</h1>
            <p class="mt-2 text-sm text-surface-fg-soft">
                Details on your database server, edition, and more.
            </p>
        </div>
    </div>
    <div class="mt-4 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-surface-fg sm:pl-0">Hostname</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Edition</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Clustered</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Multi-User</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-surface-fg sm:pl-0">
                                {{ $result->HostName ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->Edition ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->Clustered ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm sm:pr-0 text-muted font-semibold">
                                @if($result->UserMode === 'Multi user')
                                    <span class="px-2 py-1 bg-success-soft rounded text-success">{{ $result->UserMode ?? '' }}</span>
                                @else
                                    <span class="text-danger">{{ $result->UserMode ?? '' }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-sm text-muted overscroll-x-auto">
                                {!! $result->Version ?? '' !!}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
