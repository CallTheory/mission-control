@php
use Carbon\Carbon;
@endphp
<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Database Details</h1>
            <p class="mt-2 text-sm text-gray-700">
                Details on the individual databases your user has access to.
            </p>
        </div>
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">ID</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Database</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Owner</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">User Access</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Compatability</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Recovery</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Size</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                {{ $result->database_id ?? '' }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $result->database_name ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                @if($result->created_date)
                                    <span class="cursor-help" title="{{ Carbon::parse($result->created_date)->format("m/d/Y g:i:s A T") }}">{{ Carbon::parse($result->created_date)->diffForHumans() }}</span>
                                @else
                                    Unknown
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $result->owner ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $result->user_access_desc ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $result->compatibility_level ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $result->recovery_model_desc ?? '' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm sm:pr-0 text-gray-500">
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
