@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
@endphp

@if(is_object($details))
<div class="border border-gray-300 shadow overflow-hidden sm:rounded-lg mt-4 w-1/2">
    <div class="px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-gray-200 ">
            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Call Kind
                </dt>
                <dd class="font-semibold mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {{ $ck[$details->Kind ?? 0] }}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Start Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {{ Carbon::parse($details->CallStart, $timezone)->timezone(Auth::user()->timezone)->format("m/d/Y g:i:s A T") }}
                </dd>
            </div>
            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    End Time
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {{ Carbon::parse($details->CallEnd, $timezone)->timezone(Auth::user()->timezone)->format("m/d/Y g:i:s A T") }}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Timezone Offset
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $details->TimezoneOffset ?? '<small class="text-indigo-600 group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    ANI
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $details->CallerANI ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!} {{ $details->CallerName ?? '' }}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    ANI Name
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    @if(strlen( $details->CallerName) === 0)
                        <small class="text-indigo-600 group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>
                    @else
                        {!! $details->CallerName ??  '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    DNIS
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $details->CallerDNIS ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Diversion
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $details->Diversion ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}  {{ $details->DiversionReason ?? '' }}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Channel
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $details->Channel ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Skill Name
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    @if($details->SkillId == 0 )
                        <small class="text-indigo-600 group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>
                    @else
                        {!! $details->SkillName ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                    @endif

                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Station Type
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    @if(isset($st[$details->stationType]))
                        {!! $st[$details->stationType] !!}

                    @else
                        {!! $details->stationType ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                    @endif
                </dd>
            </div>

            <div class="group py-2 sm:py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6  transform transition ease-in-out duration-700">
                <dt class="text-sm font-medium text-gray-500 0  transform transition ease-in-out duration-700">
                    Completion Code
                </dt>
                <dd class="mt-1 text-sm text-gray-900   transform transition ease-in-out duration-700 sm:mt-0 sm:col-span-2">
                    {!! $cc[$details->CompCode] ?? '<small class="text-gray-300  group-hover:text-indigo-500 transform transition duration-700 ease-in-out">Not Set</small>'  !!}
                </dd>
            </div>


        </dl>
    </div>
</div>
@endif
