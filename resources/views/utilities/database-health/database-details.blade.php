@php
use Carbon\Carbon;
@endphp
<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-surface-fg">Database Details</h1>
            <p class="mt-2 text-sm text-surface-fg-soft">
                Details on the individual databases your user has access to.
            </p>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-surface-fg sm:pl-0">ID</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Database</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Created</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Owner</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">User Access</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Compatability</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Recovery</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Size</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-surface-fg sm:pl-0">
                                {{ $result->database_id ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->database_name ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                @if($result->created_date)
                                    <span class="cursor-help" title="{{ Carbon::parse($result->created_date)->format("m/d/Y g:i:s A T") }}">{{ Carbon::parse($result->created_date)->diffForHumans() }}</span>
                                @else
                                    Unknown
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->owner ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->user_access_desc ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->compatibility_level ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                {{ $result->recovery_model_desc ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm sm:pr-0 text-muted">
                                {{ round($result->DBSizeInMB ?? 0, 2) }} MB
                            </td>
                        </tr>

                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
