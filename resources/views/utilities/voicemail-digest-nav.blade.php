<div class="border-b border-border">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-surface-fg">Voicemail Digest</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-primary text-primary";
                    $default = "border-transparent text-muted hover:border-border hover:text-surface-fg-soft";
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
