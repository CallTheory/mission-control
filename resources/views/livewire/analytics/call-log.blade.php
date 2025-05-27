@php
    use Carbon\Carbon;
    use App\Models\System\Settings;
    use App\Models\Stats\Helpers;
    $settings = Settings::first();

@endphp
<div class="w-full inline-block">

    <div class="p-4 bg-gray-50 rounded shadow">

        <div class="inline-flex flex">
            <div class="mr-4 pr-4">
                <x-label for="start_date">
                    Start Date <small class="text-gray-400">({{ $timezone }})</small>
                </x-label>
                <x-input id="start_date" wire:loading.attr="disabled" type="datetime-local" class="my-1" wire:model.defer="start_date"  />
                <x-input-error for="start_date" class="mt-2" />
            </div>
            <div class="mr-4 pr-4">
                <x-label for="end_date">
                    End Date <small class="text-gray-400">({{ $timezone }})</small>
                </x-label>
                <x-input id="end_date" wire:loading.attr="disabled" type="datetime-local" class="my-1" wire:model.defer="end_date" />
                <x-input-error for="end_date" class="mt-2" />
            </div>
            <div class="mr-4 pr-4">
                <x-label for="client_number">Client Number</x-label>
                <x-input id="client_number" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="client_number" />
                <x-input-error for="client_number" class="mt-2" />
            </div>
            <div class="mr-4 pr-4">
                <x-label for="search_button">Filter</x-label>
                <x-button id="search_button" wire:loading.attr="disabled" class="mt-2" wire:click="applyFilter">
                    Apply Filter
                </x-button>
                <x-secondary-button id="reset_filter" wire:loading.attr="disabled" class="mt-2" wire:click="resetFilter">
                    Reset
                </x-secondary-button>
                @if($sql_code)
                    <div class="inline"
                        x-data="{ 'showSQLCode': false }"
                        @keydown.escape="showSQLCode = false"
                    >

                        <!-- Trigger for Modal -->
                        <x-secondary-button class="mt-2 inline"  @click="showSQLCode = true">
                            &lt;SQL&gt;
                        </x-secondary-button>

                        <!-- Modal -->
                        <div
                            class="fixed inset-0 z-100 overflow-scroll bg-black text-white"
                            x-show="showSQLCode"
                            @click.away="showSQLCode = false"
                            x-transition:enter="motion-safe:ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-90"
                            x-transition:enter-end="opacity-100 scale-100"
                        >
                            <div class="p-4">
                                <div class="flex">
                                    <button type="button" class="hover:text-gray-700  text-gray-500 z-50 ml-0 cursor-pointer"
                                            onclick="navigator.clipboard.writeText('{{ str_replace("\n", "\\n", $sql_code) }}');">
                                        Copy SQL Code
                                    </button>
                                    <button type="button" class="z-50 ml-auto cursor-pointer" @click="showSQLCode = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <hr class="my-4 border border-gray-300" />

                                <div>
                                    <code id="sql_code">{!! nl2br($sql_code) !!}</code>
                                </div>

                                <hr class="my-4 border border-gray-300" />

                                <div>
                                    <pre>{!! print_r($sql_params, true) !!}</pre>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif

                <x-action-message class="ml-2 inline" on="saved">
                    <span class="text-green-500">&checkmark;</span>
                </x-action-message>
            </div>
        </div>

        <div class="inline-flex flex my-2">
            <div class="mr-4 pr-4">
                <x-label for="ani">ANI</x-label>
                <x-input id="ani" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="ani" />
                <x-input-error for="ani" class="mt-2" />
            </div>
            <div class="mr-4 pr-4">
                <x-label for="call_type">Call Type</x-label>
                <select id="call_type" wire:loading.attr="disabled"  class="mt-1 rounded border border-gray-300 shadow" wire:model.defer="call_type">
                    <option value=""></option>
                    @foreach($ck as $ctid => $ctname)
                        <option value="{{ $ctid }}">{{ $ctname }}</option>
                    @endforeach
                </select>
                <x-input-error for="call_type" class="mt-2" />
            </div>

            <div class="mr-2 pr-2">
                <x-label for="min_duration">Min. Duration <small class="text-gray-400">second(s)</small></x-label>
                <x-input id="min_duration" wire:loading.attr="disabled" type="text" class="mt-1" wire:model.defer="min_duration" />
                <x-input-error for="min_duration" class="mt-2" />
            </div>
            <div class="mr-4 pr-4">
                <x-label for="max_duration">Max. Duration <small class="text-gray-400">second(s)</small></x-label>
                <x-input id="max_duration" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="max_duration" />
                <x-input-error for="max_duration" class="mt-2" />
            </div>
        </div>

        <div class="inline-flex flex">
            @if($agents)
                <div class="mr-4 pr-4">
                    <x-label for="agent">Agent</x-label>
                    <select id="agent" wire:loading.attr="disabled"  class="mt-1 rounded border border-gray-300 shadow" wire:model.defer="agent">
                        <option value=""></option>
                        @foreach($agents as $agentDetails)
                            <option value="{{ $agentDetails->agtId }}">{{ $agentDetails->Name }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="agent" class="mt-2" />
                </div>
            @endif

            @if($keywords)
                <div class="mr-2 pr-2">
                    <x-label for="keyword">Keyword Label</x-label>
                    <select id="keyword" wire:loading.attr="disabled"  class="mt-1 rounded border border-gray-300 shadow" wire:model.defer="keyword">
                        <option value=""></option>
                        @foreach($keywords as $kw)
                            <option value="{{ $kw->Keywords }}">{{ $kw->Keywords }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="agent" class="mt-2" />
                </div>
            @endif

            <div class="mr-4 pr-4">
                <x-label for="keyword_search">Keyword Value</x-label>
                <x-input id="keyword_search" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="keyword_search" />
                <x-input-error for="keyword_search" class="mt-2" />
            </div>
        </div>

        <div class="block mt-4">
            <fieldset class="flex">
                <legend class="text-sm text-gray-500 sr-only">
                    Record Attributes
                </legend>
                <div class="space-x-5 flex">
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input x-on:click="$wire.has_messages=false;$wire.has_recordings=false;$wire.has_video=false;"  wire:model="has_any" id="has_any" aria-describedby="has_any-description" name="has_any" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-1 text-sm leading-6">
                            <label for="has_messages" class="font-medium text-gray-900 flex whitespace-nowrap align-text-top">
                                <svg title="Any attribute"  xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 text-sm text-gray-400 m-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                </svg> Any
                            </label>
                            <p id="has_any-description" class="text-gray-500 sr-only">Include any attribute</p>
                        </div>
                    </div>
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input x-on:click="$wire.has_any=false;" wire:model="has_messages" id="has_messages" aria-describedby="has_messages-description" name="has_messages" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-1 text-sm leading-6">
                            <label for="has_messages" class="font-medium text-gray-900 flex whitespace-nowrap align-text-top"><svg title="Message(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 text-sm text-gray-400 m-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg> Message(s)</label>
                            <p id="has_messages-description" class="text-gray-500 sr-only">The call has at least one associated message taken.</p>
                        </div>
                    </div>
                    <div class="relative flex items-start ml-4">
                        <div class="flex h-6 items-center">
                            <input x-on:click="$wire.has_any=false;" wire:model="has_recordings" id="has_recordings" aria-describedby="has_recordings-description" name="has_recordings" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-1 text-sm leading-6">
                            <label for="has_recordings" class="font-medium text-gray-900 flex whitespace-nowrap"> <svg title="Recording(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 text-sm text-gray-400 m-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                                </svg> Recording(s)</label>
                            <p id="has_recordings-description" class="text-gray-500 sr-only">The call has at least one associated recording.</p>
                        </div>
                    </div>
                    <div class="relative flex items-start ml-4">
                        <div class="flex h-6 items-center">
                            <input x-on:click="$wire.has_any=false;" wire:model="has_video" id="has_video" aria-describedby="has_video-description" name="has_video" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-1 text-sm leading-6">
                            <label for="has_video" class="font-medium text-gray-900 flex whitespace-nowrap"> <svg title="Screen Capture(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 text-sm text-gray-400 m-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M19.125 12h1.5m0 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h1.5m14.25 0h1.5" />
                                </svg> Video</label>
                            <p id="has_video-description" class="text-gray-500 sr-only">The call has video screen capture available.</p>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="mx-2">
        <div class="my-2">
            {{ $call_log->links() }}
        </div>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200  text-center">
                <thead class="">
                <tr class="sticky top-0">
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Call Record
                    </th>
                    <th scope="col" class="flex px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Call Start &nbsp;
                        <button wire:click="setSorting('Stamp','asc')">
                            <svg  xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 @if($sort_by === 'statCallStart.Stamp' && $sort_direction === 'asc') {{ 'text-black' }} @else {{ 'text-gray-400' }} @endif">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18" />
                            </svg>
                        </button>
                        <button wire:click="setSorting('Stamp','desc')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 @if($sort_by === 'statCallStart.Stamp' && $sort_direction === 'desc') {{ 'text-black' }} @else {{ 'text-gray-400' }} @endif">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3" />
                            </svg>
                        </button>

                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Caller
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                        Client
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Agent(s)
                    </th>
                    <th scope="col" class="flex px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Duration &nbsp;
                        <button wire:click="setSorting('Duration','desc')">
                            <svg  xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 @if($sort_by === 'CallDuration' && $sort_direction === 'desc') {{ 'text-black' }} @else {{ 'text-gray-400' }} @endif">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18" />
                            </svg>
                        </button>
                        <button wire:click="setSorting('Duration','asc')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 @if($sort_by === 'CallDuration' && $sort_direction === 'asc') {{ 'text-black' }} @else {{ 'text-gray-400' }} @endif">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3" />
                            </svg>
                        </button>

                    </th>

                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 ">
                @forelse($call_log as $recent)
                    <tr class="group  transform transition duration-700 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900  transform transition duration-700 ease-in-out">
                            <a class="hover:cursor-pointer font-semibold hover:underline text-indigo-500 hover:text-indigo-700 transform transition duration-700 ease-in-out" href="/utilities/call-lookup/{{ $recent->CallId }}">
                                {{ $recent->CallId }}
                            </a>
                            <div class="block">
                                <div class="inline-flex whitespace-nowrap my-2 text-xs font-semibold px-2 py-1 border border-gray-300 rounded-full">
                                    {{ $ck[$recent->Kind] ?? 'Unknown' }}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-900  transform transition duration-700 ease-in-out">
                            {{ Carbon::parse($recent->CallStart, $timezone)->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y') }}
                            <div class="block whitespace-nowrap my-2">
                                {{ Carbon::parse($recent->CallStart, $timezone)->timezone(Auth::user()->timezone ?? 'UTC')->format('g:i:s A T') }}
                            </div>
                        </td>

                        <td class="px-6 py-4 text-sm text-gray-900   transform transition duration-700 ease-in-out">
                            @if($recent->CallerANI)
                                <code class="font-monospace font-semibold">{!!   $recent->CallerANI ?? '' !!}</code>  @if($recent->CallerName ?? '') <div class="block whitespace-nowrap my-2">{{ $recent->CallerName }}</div>  @endif
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif

                        </td>
                        <td class="px-6 py-4 break-normal text-sm text-gray-900   transform transition duration-700 ease-in-out">
                            <span class="font-semibold">{{ $recent->ClientNumber }}</span>
                            <div class="block  my-2">
                                {{ $recent->ClientName }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">

                            @php
                                $agent_list = explode(',', $recent->AgentList);
                            @endphp
                            @forelse($agent_list as $agent_details)
                               @php
                                    $agent_on_call = explode('-', $agent_details);
                                    //[0] agtId
                                    //[1] agtName
                                    //[2] agtInitials
                                    //[3] stnNumber
                                    //[4] stnType
                               @endphp
                                @if(isset($agent_on_call[1]) && isset($agent_on_call[2]))
                                    <div class="block my-1">
                                        <span class="font-semibold">{{ $agent_on_call[1] ?? '' }} ({{ $agent_on_call[2] ?? '' }})</span>
                                    </div>
                                @endif
                            @empty
                                <span class="text-gray-400">&mdash;</span>
                            @endforelse

                        </td>

                        <td class="px-6 py-4 text-md font-mono font-medium text-gray-900   transform transition duration-700 ease-in-out">
                            <div class="block">
                                {{ Helpers::formatDuration($recent->CallDuration ?? 0) }}
                            </div>
                            <div class="inline-flex my-2 whitespace-nowrap">
                                <svg title="Message(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 @if($recent->hasMessages) {{ 'text-indigo-500 fill-indigo-50' }}@else {{ 'text-gray-300' }} @endif">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <svg title="Recording(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 @if($recent->hasRecordings) {{ 'text-indigo-500 fill-indigo-50' }}@else {{ 'text-gray-300' }} @endif">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                                </svg>
                                <svg title="Screen Capture(s)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 @if($recent->hasVideo) {{ 'text-indigo-400 fill-indigo-50' }}@else {{ 'text-gray-200' }} @endif">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M19.125 12h1.5m0 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h1.5m14.25 0h1.5" />
                                </svg>

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="my-2">
            {{ $call_log->links() }}
        </div>
    </div>
</div>
