<div class="border-b border-gray-300">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-gray-900">Message Export</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-indigo-500 text-indigo-600";
                    $default = "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700";
                @endphp

                <a href="/utilities/message-export"
                   @if(request()->is('utilities/message-export') && !request()->is('utilities/message-export/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/message-export') && !request()->is('utilities/message-export/history') ? $current : $default }}">
                    Exports
                </a>

                <a href="/utilities/message-export/history"
                   @if(request()->is('utilities/message-export/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/message-export/history') ? $current : $default }}">
                    Export History
                </a>
            </nav>
        </div>
    </div>
</div>
