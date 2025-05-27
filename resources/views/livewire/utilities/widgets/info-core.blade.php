<h2 class="font-semibold text-indigo-900">CallID {{ $isCallID ?? '--------' }}</h2>
<h3 class="text-2xl  font-bold">{{ $details->ClientName ?? 'Unknown Client' }}</h3>
<h4 class="font-semibold text-indigo-700">Account {{ $details->ClientNumber ?? '0000' }}</h4>
<h5 class="font-thin text-indigo-500">Billing Code <strong class="font-semibold">{{ $details->BillingCode ?? '0000' }}</strong></h5>
@if(count($agents))
    <small class="text-gray-400">Agent(s):</small>
    @foreach($agents as $agent )
        @if(is_object($agent))
            <div
                class="my-2 inline-flex border border-gray-300 bg-gray-200 rounded text-gray-800 shadow cursor-help
                                      duration-700 transition transform ease-in-out py-0.5 px-1 text-xs ml-1"
                title="Agent Initials: {{ $agent->Initials ?? '???' }}">
                {{ ucwords($agent->Name) ?? '???' }}
            </div>
        @endif
    @endforeach
@endif
