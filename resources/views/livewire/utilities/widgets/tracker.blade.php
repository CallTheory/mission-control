@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
@endphp

@if(count($tracker) && is_object($tracker[0]))
<div class="w-full mb-4  shadow rounded">
    <div class="table min-w-full divide-y divide-gray-200 border border-gray-300 w-full rounded">
        <div class="bg-gray-50   table-row-group">
            <div class="table-row">
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Stamp
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    CallId
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Client
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Kind
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Type
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    State
                </div>


                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Agent
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Station
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                    Details
                </div>
            </div>

            @foreach($tracker as $t)
                @if(is_object($t))
                    <div class=" bg-white group
                                     hover:bg-gray-200 transform transition duration-700 ease-in-out table-row ">
                        <div class="table-cell px-6 py-1 text-xs break-words font-medium text-gray-900  border-b border-gray-300 ">
                            {{ Carbon::parse($t->Stamp, $timezone)->timezone(Auth::user()->timezone)->format("m/d/Y g:i:s.u A T")  }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-normal text-gray-900  border-b border-gray-300">
                            {{ $t->CallId }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-gray-900  border-b border-gray-300">
                            {{ $t->clientNumber }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-gray-900  border-b border-gray-300">
                            {{ $ck[$t->callType] ?? $t->callType }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-thin text-gray-900  border-b border-gray-300">
                            {{ $tt[$t->type] ?? $t->type }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-thin text-gray-900  border-b border-gray-300">
                            {{ $cs[$t->callState] ?? $t->callState }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-xs font-medium text-gray-900  border-b border-gray-300">
                            @if($t->Name)
                                {{ $t->Name }} <small>({{ $t->Initials }})</small>
                            @endif
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-gray-900  border-b border-gray-300">
                            @if($t->stationNumber >= 1 )
                                <small>{{ $st[$t->stationType] ?? $t->stationType }} <span class="text-indigo-300">{{ $t->stationNumber }}</span></small>
                            @endif
                        </div>
                        <div class="table-cell px-6 py-1 wrap text-xs break-words font-normal text-gray-900  border-b border-gray-300">
                            {{ $t->value }}
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

</div>
@endif
