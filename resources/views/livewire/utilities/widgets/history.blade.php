@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
@endphp

@if(count($history) && is_object($history[0]))
    <div class="w-1/2 mb-2 max-h-screen block border border-gray-300 rounded overflow-y-auto">

        <div class="flex px-2 py-2 my-2 align-middle border-b border-gray-300">
            <svg class="w-5 h-5 mx-0 inline-flex text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <strong>History</strong>
        </div>

        @foreach($history as $h)
            @if(is_object($h))
                <div class="mb-2 bg-gray-100  mx-2  rounded px-1 py-2">

                    <div class="block text-xs">
                        <small>
                            <span class="text-indigo-700 ">{{ $h->Name }} ({{ $h->Initials }})</span> &middot; <span class="text-gray-500  w-full">{{ Carbon::parse($h->Stamp, $timezone)->timezone(Auth::user()->timezone)->format("m/d/Y g:i:s.u A T") }}</span>
                        </small>
                    </div>

                   <div class="inline text-xs  text-black font-semibold">
                       {{ Str::headline($h->Disposition) }}
                   </div>

                </div>
            @endif
        @endforeach

    </div>
@endif
