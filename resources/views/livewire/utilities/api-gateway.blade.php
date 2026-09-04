<div class="min-w-full w-full">
    <h2 class="text-sm font-semibold text-surface-fg mx-2">Scripting API Integrations</h2>
    <p class="text-xs text-muted mx-2 my-2">
        A collection of 1st-party utility APIs and 3rd-party <strong>BYOK API workflows </strong> (Bring Your Own Keys) that integrate into Intelligent Series scripting for use with any number of clients.
    </p>
    <ul role="list" class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 md:grid-cols-4 lg:grid-cols-2 mx-2">
        @foreach($apis as $api)
            <li class="col-span-1 flex rounded-md shadow">
                <div class="flex w-16 shrink-0 items-center justify-center bg-surface-3 rounded-l-md text-sm font-medium text-surface-fg">
                    <img class="p-2" src="{{ $api['logo'] ?? '/images/call-theory.png' }}" />
                </div>
                <div class="flex flex-1 items-center justify-between break-WORDS rounded-r-md border-b border-r border-t border-border bg-surface">
                    <div class="flex-1 px-4 py-2 text-sm">
                        <a href="{{ $api['docs'] ?? '#' }}" class="text-surface-fg hover:text-surface-fg-soft font-semibold">
                            {{ $api['name'] ?? 'Unknown' }}
                        </a>
                        <h3 class=" text-muted">{{ $api['api'] ?? 'Unknown' }}</h3>
                        <p class="text-surface-fg-soft text-xs">
                            {{ $api['description'] ?? 'Unknown' }}
                        </p>
                        <code class="text-primary text-xs">
                            {{ $api['example'] ?? '' }}
                        </code>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
