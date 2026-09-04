@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
@endphp

@if(count($tracker) && is_object($tracker[0]))
<div class="w-full mb-4  shadow rounded">
    <div class="table min-w-full divide-y divide-border-soft border border-border w-full rounded">
        <div class="bg-surface-2 table-row-group">
            <div class="table-row">
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Stamp
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    CallId
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Client
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Kind
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Type
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    State
                </div>


                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Agent
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Station
                </div>
                <div scope="col" class="table-cell px-4 py-2 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                    Details
                </div>
            </div>

            @foreach($tracker as $t)
                @if(is_object($t))
                    <div class=" bg-surface group
                                     hover:bg-surface-3 transform transition duration-700 ease-in-out table-row ">
                        <div class="table-cell px-6 py-1 text-xs break-words font-medium text-surface-fg border-b border-border ">
                            {{ Carbon::parse($t->Stamp, $timezone)->timezone(Auth::user()->timezone)->format("m/d/Y g:i:s.u A T")  }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-normal text-surface-fg border-b border-border">
                            {{ $t->CallId }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-surface-fg border-b border-border">
                            {{ $t->clientNumber }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-surface-fg border-b border-border">
                            {{ $ck[$t->callType] ?? $t->callType }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-thin text-surface-fg border-b border-border">
                            {{ $tt[$t->type] ?? $t->type }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-thin text-surface-fg border-b border-border">
                            {{ $cs[$t->callState] ?? $t->callState }}
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-xs font-medium text-surface-fg border-b border-border">
                            @if($t->Name)
                                {{ $t->Name }} <small>({{ $t->Initials }})</small>
                            @endif
                        </div>
                        <div class="table-cell px-6 py-1 whitespace-nowrap text-sm font-medium text-surface-fg border-b border-border">
                            @if($t->stationNumber >= 1 )
                                <small>{{ $st[$t->stationType] ?? $t->stationType }} <span class="text-primary">{{ $t->stationNumber }}</span></small>
                            @endif
                        </div>
                        <div class="table-cell px-6 py-1 wrap text-xs break-words font-normal text-surface-fg border-b border-border">
                            {{ $t->value }}
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

</div>
@endif
