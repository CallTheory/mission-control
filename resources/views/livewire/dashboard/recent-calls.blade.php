@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades;
    use App\Models\Stats\Helpers;

    use App\Models\System\Settings;

    $ck = Helpers::callTypes();

    $settings = Settings::first();

    if(!is_null($settings))
    {
        $switchTimezone = $settings->switch_data_timezone ?? 'UTC';
    }
    else
    {
        $switchTimezone = 'UTC';
    }

@endphp
<div class="w-full my-2 inline-block" wire:poll.10000ms.visible wire:id="{{ uniqid('widget') }}">
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                @if(count($recentCalls))

                    <h3 class="text-2xl  my-2">Recent Calls</h3>

                <div class="shadow overflow-hidden border-b border-gray-300  sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200  text-center">
                        <thead class=" bg-gray-100">
                        <tr class="sticky top-0">
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">callId</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Call Start</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                                Call Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                                ANI
                            </th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Client Number</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Client Name</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Agent</th>

                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Skill Name</th>

                        </tr>
                        </thead>
                        <tbody class="bg-white  divide-y divide-gray-200 ">

                        @foreach($recentCalls as $recent)
                            <tr class="group hover:bg-gray-100  transform transition duration-700 ease-in-out">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    <a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out" href="/utilities/call-lookup/{{ $recent->CallId }}">
                                        {{ $recent->CallId }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    <span title="{{ Carbon::parse($recent->CallStart, $switchTimezone)->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y g:i:s A T') }}">
                                        {{ Carbon::parse($recent->CallStart, $switchTimezone)->timezone(Auth::user()->timezone ?? 'UTC')->diffForHumans(\Carbon\Carbon::now(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 4 ) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $ck[$recent->Kind] ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!!  $recent->CallerANI ?? '&mdash;'  !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $recent->ClientNumber }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    <a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out" href="/analytics/client-accounts/{{ $recent->ClientNumber }}">
                                        {{ $recent->ClientName }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $recent->AgentName }} @if($recent->AgentInitials) ({{ $recent->AgentInitials }}) @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $recent->SkillName }}
                                </td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <h3 class="text-xl text-gray-400  my-2">No recent calls found...</h3>
                @endif
            </div>
        </div>
    </div>
</div>
