@php
    use App\Models\Stats\Helpers;
    use Carbon\Carbon;
@endphp
<div class="w-full my-2 block"  wire:poll.30000ms.visible="getStats">

    @push('scripts')

        <script>

            document.addEventListener('DOMContentLoaded', (loaded) => {
                updateData();
            });

            Livewire.on('sparkline', (event) => {
               updateData();
            });

            function updateData()
            {
                let holdData = [
                    @foreach(array_reverse($state['on_hold_time']) as $item )
                        {name: "On-Hold Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let secretarialData = [
                    @foreach(array_reverse($state['secretarial_time']) as $item )
                        {name: "Secretarial Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let checkinData = [
                    @foreach(array_reverse($state['checkin_time']) as $item )
                        {name: "Checkin Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let outboundData = [
                    @foreach(array_reverse($state['outbound_time']) as $item )
                        {name: "Outbound Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let patchData = [
                    @foreach(array_reverse($state['patch_time']) as $item )
                        {name: "Patch Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let nonliveData = [
                    @foreach(array_reverse($state['nonlive_time']) as $item )
                        {name: "Non-Live Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time']}}},
                    @endforeach
                ];

                let activityData = [
                    @foreach(array_reverse($state['total_time']) as $item )
                        {name: "Total Activity Time", date: "{{ $item['date']->format('Y-m-d') }}", value: {{ $item['time'] }}},
                    @endforeach
                ];

                let options = {
                    //spotRadius: 5,
                    //cursorWidth: 10,
                    interactive: true
                };

                sparkline(document.querySelector(".sparkline-on-hold-time"), holdData, options);
                sparkline(document.querySelector(".sparkline-secretarial-minutes-time"), secretarialData, options);
                sparkline(document.querySelector(".sparkline-checkin-minutes-time"), checkinData, options);
                sparkline(document.querySelector(".sparkline-outbound-minutes-time"), outboundData, options);
                sparkline(document.querySelector(".sparkline-patch-minutes-time"), patchData, options);
                sparkline(document.querySelector(".sparkline-nonlive-minutes-time"), nonliveData, options);
                sparkline(document.querySelector(".sparkline-total-activity-time"), activityData, options);

            }

        </script>
    @endpush


    <div class="bg-white  shadow overflow-hidden sm:rounded-lg mx-2 ">

        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 ">
                Call Analytics
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 0">
                Summarized call data for your team.
            </p>
        </div>

        <div class="border-t-4 border-gray-300  px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200 sm:">

                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">

                    <dt class="text-sm font-medium text-gray-500  flex">
                        <span class="text-nowrap w-50">On-Hold Time</span>
                        @include('layouts.sparkline', [
                            'className' => 'sparkline-on-hold-time',
                            'strokeColor' => '#5EEAD4',
                        ])
                    </dt>

                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['on_hold_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>

                                </div>

                            @endforeach

                        </div>
                    </dd>

                </div>



                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">
                    <dt class="text-sm font-medium text-gray-500 ">
                        Secretarial Minutes
                        @include('layouts.sparkline', [
                            'className' => 'sparkline-secretarial-minutes-time',
                            'strokeColor' => '#5EEAD4',
                        ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['secretarial_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>

                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">
                    <dt class="text-sm font-medium text-gray-500 ">
                        Checkin Minutes
                        @include('layouts.sparkline', [
                           'className' => 'sparkline-checkin-minutes-time',
                           'strokeColor' => '#5EEAD4',
                       ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['checkin_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>


                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">
                    <dt class="text-sm font-medium   text-gray-500 ">
                        Outbound Minutes
                        @include('layouts.sparkline', [
                           'className' => 'sparkline-outbound-minutes-time',
                           'strokeColor' => '#5EEAD4',
                       ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['outbound_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>


                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">

                    <dt class="text-sm font-medium   text-gray-500 ">
                        Patch Minutes
                        @include('layouts.sparkline', [
                           'className' => 'sparkline-patch-minutes-time',
                           'strokeColor' => '#5EEAD4',
                       ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['patch_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>

                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">

                    <dt class="text-sm font-medium   text-gray-500 ">
                        Non-Live Minutes
                        @include('layouts.sparkline', [
                           'className' => 'sparkline-nonlive-minutes-time',
                           'strokeColor' => '#5EEAD4',
                       ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['nonlive_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>

                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 transform transition duration-700 ease-in-out hover:bg-indigo-800 group">
                    <dt class="text-sm font-medium   text-gray-500  flex">
                        Total Agent Activity
                        @include('layouts.sparkline', [
                           'className' => 'sparkline-total-activity-time',
                           'strokeColor' => '#5EEAD4',
                       ])
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900  sm:mt-0 sm:col-span-2">

                        <div class="flex grid grid-cols-8 gap-2 ">

                            @foreach(array_reverse($state['total_time']) as $item )
                                <div class=" transform transition duration-700 ease-in-out">

                                    <small class="text-xs block 0  transform transition duration-700 ease-in-out">
                                        @if($item['date']->format('Y-m-d') === Carbon::today(Auth::user()->timezone)->format('Y-m-d'))
                                            <span class="text-indigo text-indigo-400  font-semibold transform transition duration-700 ease-in-out">
                                                Today
                                            </span>
                                            <span class="block text-white">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @else
                                            {{ $item['date']->format('D') }}
                                            <span class="block text-indigo-300  transform transition duration-700 ease-in-out">{{ Helpers::formatDuration( $item['time'] ) }}</span>
                                        @endif
                                    </small>
                                </div>

                            @endforeach

                        </div>
                    </dd>
                </div>

            </dl>
        </div>
    </div>

</div>
