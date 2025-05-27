@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
@endphp
<div class="px-4 ">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Intelligent Maintenance</h1>
            <p class="mt-2 text-sm text-gray-700">
                Details on your scheduled Intelligent Maintenance items, such as Archive and Purge. Use Intelligent Series Supervisor to manage these items.
            </p>
        </div>
    </div>
    <div class="block bg-gray-50 my-4 rounded shadow shadow p-4 border border-gray-300">
        <h3 class="text-sm font-semibold text-gray-600 my-2">Intelligent Series Checklist</h3>
        <p class="text-sm text-gray-500 mb-4">A quick check to confirm that recommended Intelligent Series maintenance actions are scheduled.</p>
        @foreach($maintenance_checklist as $item => $enabled)
            @if($enabled === true)
                <div class="inline-flex  bg-green-500 text-sm text-white rounded-lg mr-2 px-2 py-1 shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 mr-1 my-auto">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                    </svg>
                    {{ Str::headline($item) }}
                </div>
            @else
                <div class="inline-flex bg-red-500 text-sm text-white rounded-lg mr-2 px-2 py-1 shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 mr-1 my-auto">
                        <path fill-rule="evenodd" d="M3 2.25a.75.75 0 0 1 .75.75v.54l1.838-.46a9.75 9.75 0 0 1 6.725.738l.108.054A8.25 8.25 0 0 0 18 4.524l3.11-.732a.75.75 0 0 1 .917.81 47.784 47.784 0 0 0 .005 10.337.75.75 0 0 1-.574.812l-3.114.733a9.75 9.75 0 0 1-6.594-.77l-.108-.054a8.25 8.25 0 0 0-5.69-.625l-2.202.55V21a.75.75 0 0 1-1.5 0V3A.75.75 0 0 1 3 2.25Z" clip-rule="evenodd" />
                    </svg>
                    {{ Str::headline($item) }}
                </div>
            @endif

        @endforeach
    </div>
    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Scheduled</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Action</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Recurrence</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @foreach($results as $result )
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-700 sm:pl-0">
                                {{ Carbon::parse($result->Scheduled, $switch_timezone)->timezone(Auth::user()->timezone)->format('D, F jS Y g:i:s A T') }}
                            </td>

                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <code class="bg-gray-700 px-2 py-1 rounded text-white">{{ Str::headline($scheduleTypes[$result->Action ?? -1]) }}</code>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $scheduleRecurrenceTypes[$result->RecurrenceType ?? 0] }}
                                <span class="hidden">{{ $result->RecurrenceMask ?? '' }}  {{ $result->RecurrenceInterval ?? '' }}</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
