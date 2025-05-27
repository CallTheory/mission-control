@php
    use Illuminate\Support\Facades\Auth;
    use App\Models\Stats\Helpers;
    use Carbon\Carbon;
@endphp

@if(isset($statistics[0]) && is_object($statistics[0]))
<div class="bg-white border border-gray-300 shadow overflow-hidden sm:rounded-lg mt-4 w-1/2">
    <div class="px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-gray-200 ">

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Ring Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selRing + $statistics[0]->unselRing === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selRing + $statistics[0]->unselRing ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selRing + $statistics[0]->unselRing ) }}
                    @endif


                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Auto Hold
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selAutoHold + $statistics[0]->unselAutoHold === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selAutoHold + $statistics[0]->unselAutoHold ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selAutoHold + $statistics[0]->unselAutoHold ) }}
                    @endif
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Talk Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selTalk + $statistics[0]->unselTalk === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selTalk + $statistics[0]->unselTalk ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selTalk + $statistics[0]->unselTalk ) }}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Talk1 Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selTalk1 + $statistics[0]->unselTalk1 === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selTalk1 + $statistics[0]->unselTalk1 ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selTalk1 + $statistics[0]->unselTalk1 ) }}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Talk2 Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selTalk2 + $statistics[0]->unselTalk2 === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selTalk2 + $statistics[0]->unselTalk2 ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selTalk2 + $statistics[0]->unselTalk2 ) }}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    In Progress
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selInProgress + $statistics[0]->unselInProgress === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selInProgress + $statistics[0]->unselInProgress ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selInProgress + $statistics[0]->unselInProgress ) }}
                    @endif


                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Conference Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selConference + $statistics[0]->unselConference === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selConference + $statistics[0]->unselConference ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selConference + $statistics[0]->unselConference ) }}
                    @endif


                </dd>
            </div>


            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Hold Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selHold + $statistics[0]->unselHold === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selHold + $statistics[0]->unselHold ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selHold + $statistics[0]->unselHold ) }}
                    @endif


                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Disconnect Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selDisc + $statistics[0]->unselDisc === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selDisc + $statistics[0]->unselDisc ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selDisc + $statistics[0]->unselDisc ) }}
                    @endif
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Auto Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selAuto + $statistics[0]->unselAuto === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selAuto + $statistics[0]->unselAuto ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selAuto + $statistics[0]->unselAuto ) }}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Outbound Queue
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">

                    @if($statistics[0]->selOutboundQueue + $statistics[0]->unselOutboundQueue === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selOutboundQueue + $statistics[0]->unselOutboundQueue ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selOutboundQueue + $statistics[0]->unselOutboundQueue ) }}
                    @endif
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Voicemail
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    @if($statistics[0]->selVoiceMail + $statistics[0]->unselVoiceMail === 0 )
                        <small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">
                            {{ Helpers::formatDuration( $statistics[0]->selVoiceMail + $statistics[0]->unselVoiceMail ) }}
                        </small>
                    @else
                        {{ Helpers::formatDuration( $statistics[0]->selVoiceMail + $statistics[0]->unselVoiceMail ) }}
                    @endif

                </dd>
            </div>

        </dl>
    </div>
</div>
@endif
