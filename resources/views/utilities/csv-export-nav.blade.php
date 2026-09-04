<div class="border-b border-border">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-surface-fg">CSV Export</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-primary text-primary";
                    $default = "border-transparent text-muted hover:border-border hover:text-surface-fg-soft";
                @endphp

                <a href="/utilities/csv-export"
                   @if(request()->is('utilities/csv-export') && !request()->is('utilities/csv-export/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/csv-export') && !request()->is('utilities/csv-export/history') ? $current : $default }}">
                    Export
                </a>

                <a href="/utilities/csv-export/history"
                   @if(request()->is('utilities/csv-export/history')) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ request()->is('utilities/csv-export/history') ? $current : $default }}">
                    Export History
                </a>
            </nav>
        </div>
    </div>
</div>
