@php

use App\Models\MergeCommISWebTrigger;
use App\Models\InboundEmailRules;
$warning_icon = '<svg class="w-6 h-6 mx-auto rounded-full p-1 text-indigo-400 dark:group-hover:text-white ease-in-out duration-700 inline-flex mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
 $new_rule = new InboundEmailRules;
@endphp

<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <a href="/utilities">Utilities</a>
            <livewire:utilities.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div id="toggleScreenWidthContent"
             class="max-w-7xl mx-auto transform transition duration-1000 ease-in-out rounded border bg-white shadow border-gray-300">
            <div class="m-2">
                @include('layouts.width-toggle')
            </div>
            <div class="my-0 py-0 px-4">
                <x-alert-beta title="Beta Feature" description="This feature is ready for testing, feedback, and small production deployments."/>
            </div>
            <div class="my-4 py-6 px-4 bg-gray-50 rounded">
                <h3 class="font-semibold text-gray-700">Instructions for CSV Import to Database Table</h3>
                <p class="text-sm text-gray-500">All CSV file imports are done into the same database (specified in <a class="font-semibold hover:underline" href="/system/data-sources">System Data Sources</a> under Client Database Connection.)</p>
               <dl class="pb-6 text-gray-500">
                   <div class="my-4">
                       <dt class="font-bold mb-2">Replace Data In Table</dt>
                        <dd>Use <code class="px-2 py-1 bg-gray-200 rounded-lg">database:replace:TABLENAME</code> as the category to truncate TABLENAME then import the attached CSV file into TABLENAME with the same structure.<br>A failed import results in an empty table.</dd>
                   </div>
                   <div class="my-4">
                       <dt class="font-bold mb-2">Merging Data In Table</dt>
                       <dd>Use <code class="px-2 py-1 bg-gray-200 rounded-lg">database:merge:TABLENAME:COLNAME</code> as the category to import or update records from the attached CSV file into TABLENAME against COLNAME.<br>COLNAME must be unique and functions as the key to update a record or create a new one!</dd>
                   </div>
                   <div class="my-4">
                       <dt class="font-bold mb-2">Rules</dt>
                        <dd>You must include an <code>attachment</code> rule that matches a CSV file. At this time, only CSV files are supported.</dd>
                   </div>
               </dl>
            </div>

            <div class="inline-flex min-w-full px-4 mx-auto mb-4">
                <div class="flex flex-col  w-full">

                    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <h3 class="font-semibold text-2xl my-2 flex my-4">Inbound Rules&nbsp;<livewire:open-panel-button :r="$new_rule" :wire.key="uniqid()"/></h3>
                            <div class="shadow overflow-hidden border border-gray-300 rounded-lg">

                                <table class="table min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 table-row-group ">
                                        <tr class="table-row">

                                            <th class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Name
                                            </th>

                                            <th class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Category
                                            </th>

                                            <th class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Rules
                                            </th>

                                            <th class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Trigger
                                            </th>

                                            <th class="table-cell px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>

                                        </tr>
                                    </thead>
                                    <tbody>

                                        @forelse($rules as $r )

                                        <tr class="bg-white group
                                         hover:bg-gray-100 transform transition duration-500 ease-in-out table-row " >

                                            <td class="table-cell px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500">
                                                <livewire:open-panel-button :r="$r" :wire.key="$r->id"/>

                                            </td>

                                            <td class="table-cell px-6 py-4 whitespace-nowrap  text-gray-400">
                                                <span class="px-2 py-1 rounded-lg bg-gray-200 text-xs shadow-inner group-hover:text-gray-600 transform transition duration-700 ease-in-out">
                                                @if( is_null($r->category) || strlen($r->category) === 0 )
                                                    None
                                                @else
                                                    {{ $r->category  }}
                                                @endif
                                                </span>

                                            </td>
                                            <td class="table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="block p-2 shadow-inner bg-gray-200 rounded-lg min-h-full mx-1 ">
                                                    @php
                                                        $rules_array = json_decode($r->rules, true );
                                                    @endphp
                                                    @if(is_array($rules_array))
                                                        @foreach($rules_array as $parent => $filter)
                                                            @foreach($filter as $type => $values )
                                                                @foreach($values as $value)
                                                                    @if($value)
                                                                        <div class="flex-row text-xs">
                                                                            <code class="bg-gray-300 text-gray-500 rounded px-2">{{ $parent }}</code> <span class="italic font-semibold text-indigo-400">{{ $type }}</span> {{ $value }}
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            @endforeach
                                                        @endforeach
                                                    @else
                                                        {!!  $warning_icon  !!}
                                                    @endif
                                                </div>

                                            </td>
                                            <td class="table-cell text-left px-6 py-4 whitespace-nowrap text-gray-500 ">
                                                @if($r->mergecomm_trigger_id)
                                                    @php
                                                        $trigger = MergeCommISWebTrigger::find( $r->mergecomm_trigger_id);

                                                    @endphp
                                                    <div class="flex text-white">
                                                        <code class="shadow text-xs bg-indigo-700 px-2 py-1 rounded-l-lg border border-none">{{ $trigger->clientNumber ?? '' }}</code>
                                                        <code class="shadow text-xs border border-none  bg-steel-900 px-2 py-1 rounded-r-lg">MergeComm {!!  $trigger->apiKey !!}</code>
                                                    </div>
                                                @elseif($r->account)
                                                    <div class="flex dark:text-white">
                                                        <code class="shadow text-xs text-white bg-indigo-700 px-2 py-1 rounded-l-lg border border-none"> {{ $r->account ?? '' }}</code>
                                                        <code class="shadow text-xs border border-none  bg-steel-900 px-2 py-1 rounded-r-lg">Inbound SMTP</code>
                                                    </div>
                                                @else
                                                    {!!  $warning_icon  !!} Unknown
                                                @endif

                                            </td>

                                            <td class="table-cell text-center px-6 py-4 whitespace-nowrap text-sm text-gray-500">


                                                @if($r->enabled )
                                                    <span class="px-2 inline-flex mx-auto text-xs leading-5 rounded bg-green-600 text-white border border-green-500">
                                                      Enabled
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex mx-auto text-xs leading-5  rounded bg-red-600 text-white border border-red-500">
                                                      Disabled
                                                    </span>
                                                @endif
                                            </td>

                                        </tr>
                                        @empty

                                            <tr class="group hover:bg-gray-50 transform transition duration-700 ease-in-out table-row" >
                                                <td colspan="6" class="table-cell text-center px-6 py-4 whitespace-nowrap text-sm text-gray-500 ">No rules found</td>

                                            </tr>

                                    @endforelse
                                    </tbody>

                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @if( $emails->count() )
                @include('utilities.inbound-email-list')
            @else
                <div class="mx-auto text-center">
                    No Inbound Emails Found
                </div>
            @endif
        </div>
    </div>




    <livewire:email-rule />
    <livewire:email-view />
</x-app-layout>
