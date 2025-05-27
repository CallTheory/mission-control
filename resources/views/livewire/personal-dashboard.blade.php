@php
use Carbon\Carbon;
use App\Models\Stats\Helpers;
@endphp
<div>
    <div class="max-w-7xl mx-auto px-4" @saved="$refresh">
        <div class="overflow-hidden sm:rounded-lg text-gray-500 p-4">
            <div class="grid lg:grid-cols-2 gap-4">

                <div class="bg-white border border-gray-300 rounded shadow p-4 w-full min-h-full">

                    <h3 class="text-md text-gray-800 font-semibold">User Account</h3>
                    <hr class="my-2  border border-gray-300">

                    <div class="flex flex-wrap">
                        <div>
                            <div class="my-1">
                                <strong>Name</strong>: {{ $user_details['name'] }}
                            </div>
                            <div class="my-1">
                                <strong>Email</strong>: <a class="font-semibold text-indigo-500 hover:underline" href="mailto:{{ $user_details['email'] }}">{{ $user_details['email'] }}</a>
                            </div>
                            <div class="my-1">
                                <strong>Timezone</strong>: {{ $user_details['timezone'] }}
                            </div>
                            <div class="my-1">
                                <strong>Account Created</strong>: {{ Carbon::parse($user_details['created_at'], $switch_timezone)->timezone($user_details['timezone'])->format('F j, Y g:i:s A') }}
                            </div>
                            <div class="my-1">
                                <strong>Your Teams</strong>:
                                <?php
                                $userTeams = '';
                                foreach($user_details['teams'] as $team){
                                    $userTeams .= "<span class=\"bg-gray-200  text-gray-800 px-1 py-0.5 rounded text-xs mr-1\">{$team['name']} <small class=\"align-text-bottom text-gray-500\">({$team['role']})</small></span>";
                                }
                                ?>
                                {!! $userTeams !!}
                            </div>
                            <div class="mb-4">
                                <small>
                                    <a class="font-semibold text-indigo-500 hover:underline" href="/user/profile">Edit your profile</a>
                                </small>
                            </div>
                        </div>
                        <div class="mx-auto">
                            <img src="{{ $user_details['profile_photo_url'] }}" class="rounded-full border border-gray-300 shadow max-h-24" title="{{ $user_details['name'] }}" alt="{{ $user_details['name'] }}">
                        </div>
                    </div>
                </div>

                @if( count($agent_details) > 0 )
                    <div class="bg-white border border-gray-300 rounded shadow p-4 w-full min-h-full">
                        <h3 class="text-md text-gray-800 font-semibold">Intelligent Series {{ $station_types[$agent_details['agtType']] }}  <code class="text-xs align-middle font-medium bg-gray-100 border border-gray-300 px-1 py-0.5 rounded">{{ $agent_details['agtId'] }}</code></h3>
                        <hr class="my-2 border border-gray-300">

                        <div class="my-1 w-full">
                            <strong>Name</strong>: {{ $agent_details['Name'] }} ({{ $agent_details['Initials'] }})
                        </div>
                        <div class="my-1 w-full">
                            <strong>Call Limit</strong>: {{ $agent_details['CallLimit'] }}
                        </div>
                        <div class="my-1 w-full">
                            <strong>Created</strong>: {{ Carbon::parse($agent_details['Stamp'], $switch_timezone)->timezone($user_details['timezone'])->format('F j, Y g:i:s A') }}
                        </div>
                        <div class="my-1 w-full">

                            @if($agent_details['VoiceLogger'] == 1)
                                <span class="bg-green-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Voice Logger</span>
                            @else
                                <span class="bg-red-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Voice Logger Disabled</span>
                            @endif

                            @if($agent_details['AutoConnect'] == 1)
                                <span class="bg-green-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Auto-Connect</span>
                            @else
                                <span class="bg-red-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Auto-Connect Disabled</span>
                            @endif

                            @if($agent_details['LockedOut'] == 1)
                                <span class="bg-red-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Account Locked</span>
                            @else
                                <span class="bg-green-500 text-white px-1 py-0.5 rounded text-xs mr-1 whitespace-nowrap">Account Unlocked</span>
                            @endif
                        </div>
                        <div class="p-2 bg-gray-50 rounded shadow mt-2 border border-gray-300">
                            <div class="my-1 w-full">
                                <strong>Client Number</strong>: {{ $agent_details['ClientNumber'] }}
                            </div>
                            <div class="my-1 w-full">
                                <strong>Style Name</strong>: {{ $agent_details['StyleName'] }}
                            </div>
                            <div class="my-1 w-full">
                                <strong>Directory Subject</strong>: {{ $agent_details['DirectorySubject'] }} / {{ $agent_details['ViewName'] }}
                            </div>
                        </div>

                    </div>
                @else
                    <div class="bg-white border border-gray-300 rounded shadow p-4 w-full min-h-full">
                        <h3 class="text-md text-gray-800 font-semibold">Intelligent Series Agent</h3>
                        <hr class="my-2 border border-gray-300">
                        <p class="text-gray-800 leading-relaxed">
                            Your account has not been linked to an Intelligent Series agent.
                        </p>
                        <small class="block my-2 text-gray-500">
                            Please contact your administrator to link your account.
                        </small>
                    </div>
                @endif
            </div>

            @if( count($agent_details) > 0 )
                <div class="bg-white border border-gray-300 rounded shadow p-4 w-full min-h-full my-4">

                    <h3 class="text-md text-gray-800 font-semibold">Agent Activity Stream</h3>
                    <hr class="my-2 border border-gray-300">

                    <table class="w-full table-auto">
                        <thead>
                        <tr>
                            <th class="text-left leading">Stamp</th>
                            <th class="text-left leading">Agent</th>
                            <th class="text-left leading">Station</th>
                            <th class="text-left leading">Event</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($activity_stream as $stream)
                            <tr>
                                <td>
                                    {{ Carbon::parse($stream->Stamp, $switch_timezone)->timezone($user_details['timezone'])->format('F j, Y g:i:s A')}}
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($stream->Name ?? false)
                                        {{ $stream->Name }} ({{ $stream->Initials }})
                                    @else
                                        {{ $agent_details['Name'] }} ({{ $agent_details['Initials'] }})
                                    @endif
                                </td>
                                <td class="text-sm">
                                    @if($stream->StationType ?? false)
                                        {{ $station_types[$stream->StationType] }} ({{ $stream->StationId }})
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>
                                    @if($stream->TrackerType ?? false)
                                        {{ $agent_tracker_types[$stream->TrackerType] }}
                                    @else
                                        @if(Helpers::isSystemFeatureEnabled('call-lookup'))
                                            Call <a target="_blank" class="text-indigo-500 hover:underline" href="/utilities/call-lookup/{{ $stream->callID }}">{{ $stream->callID }}</a>
                                        @else
                                            Call {{ $stream->callID }}
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-left text-sm text-gray-500">No Agent Activity Stream events found</td>
                            </tr>
                        @endforelse

                        </tbody>
                    </table>
                </div>
            @endif


        </div>
    </div>
</div>
