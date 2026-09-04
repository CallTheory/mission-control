<div class="border-b border-border">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-surface-fg">Message Export</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-primary text-primary";
                    $default = "border-transparent text-muted hover:border-border hover:text-surface-fg-soft";
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
