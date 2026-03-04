<div class="border-b border-gray-300">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-gray-900">Voicemail Digest</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-indigo-500 text-indigo-600";
                    $default = "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700";
                @endphp

                <a href="/utilities/voicemail-digest"
                   @if(request()->is('utilities/voicemail-digest') && !request()->is('utilities/voicemail-digest/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/voicemail-digest') && !request()->is('utilities/voicemail-digest/history') ? $current : $default }}">
                    Schedules
                </a>

                <a href="/utilities/voicemail-digest/history"
                   @if(request()->is('utilities/voicemail-digest/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/voicemail-digest/history') ? $current : $default }}">
                    Sent History
                </a>
            </nav>
        </div>
    </div>
</div>
