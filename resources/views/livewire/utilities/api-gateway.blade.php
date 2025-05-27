<div class="min-w-full w-full">
    <h2 class="text-sm font-semibold text-gray-800 mx-2">Scripting API Integrations</h2>
    <p class="text-xs text-gray-500 mx-2 my-2">
        A collection of 1st-party utility APIs and 3rd-party <strong>BYOK API workflows </strong> (Bring Your Own Keys) that integrate into Intelligent Series scripting for use with any number of clients.
    </p>
    <ul role="list" class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 md:grid-cols-4 lg:grid-cols-2 mx-2">
        @foreach($apis as $api)
            <li class="col-span-1 flex rounded-md shadow">
                <div class="flex w-16 shrink-0 items-center justify-center bg-gray-300 rounded-l-md text-sm font-medium text-white">
                    <img class="p-2" src="{{ $api['logo'] ?? '/images/call-theory.png' }}" />
                </div>
                <div class="flex flex-1 items-center justify-between break-WORDS  rounded-r-md border-b border-r border-t border-gray-300 bg-white">
                    <div class="flex-1 px-4 py-2 text-sm">
                        <a href="{{ $api['docs'] ?? '#' }}" class="text-gray-900 hover:text-gray-600 font-semibold">
                            {{ $api['name'] ?? 'Unknown' }}
                        </a>
                        <h3 class=" text-gray-500">{{ $api['api'] ?? 'Unknown' }}</h3>
                        <p class="text-gray-700 text-xs">
                            {{ $api['description'] ?? 'Unknown' }}
                        </p>
                        <code class="text-indigo-500 text-xs">
                            {{ $api['example'] ?? '' }}
                        </code>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
