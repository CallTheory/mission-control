@php
    use Illuminate\Support\Str;
@endphp
<li class="widget relative" style="min-height: 20rem;">
    <div class="px-4 py-8 min-h-full
            group block w-full aspect-w-10 aspect-h-7 rounded-lg focus-within:ring-2
            focus-within:ring-offset-2 focus-within:ring-offset-indigo-100 focus-within:ring-indigo-500
            overflow-hidden border border-double border-gray-300  shadow bg-gray-50   mx-auto">
        <div class="mx-auto  place-content-center align-content-center align-middle h-full h-100 group">

            <div class="p-4">
                <div class="flow-root">
                    <h3 class="text-2xl text-center font-semibold mx-auto text-gray-400 mx-auto 0  transform transition ease-in-out duration-700 mt-4 mb-8">
                        Service Status
                    </h3>
                    <ul class="-my-5 divide-y divide-gray-300  place-content-center px-6">

                        @foreach($monitored as $check)
                            <li class="py-2">
                                <div class="flex items-center space-x-4">
                                    <div class="shrink-0">
                                        @if($check->status === 'up' )
                                            <div title="Everything looks good! -Famous Last Words" class="h-4 w-4 bg-green-500 border  shadow-inner rounded-full"></div>
                                        @elseif( $check->status === 'partial')
                                            <div title="A partial outage is in progress." class="h-4 w-4 bg-yellow-500 border  shadow-inner  rounded-full"></div>
                                        @elseif( $check->status === 'down')
                                            <div title="This service appears down." class="h-4 w-4 bg-red-500 border  shadow-inner rounded-full"></div>
                                        @else
                                            <div title="Unmonitored" class="h-4 w-4 bg-gray-300  border  shadow-inner rounded-full"></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium   truncate cursor-help">
                                            {{ $check->name }}
                                        </p>
                                    </div>
                                    <div>
                                        <a href="/status/{{ Str::slug($check->name) }}" class="inline-flex items-center px-2.5 py-0.5 text-gray-400 hover:text-gray-600  ">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        @endforeach



                    </ul>
                </div>
            </div>


        </div>
    </div>
</li>

