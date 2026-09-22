{{--
    Fax server switcher.

    Rendered only when there is more than one, so a single-server install — which is every
    install until someone adds a second Intelligent Series server — sees exactly the page
    it saw before.

    Styling follows the nav beside it: semantic tokens only. Dark mode is handled by
    redeclaring those tokens under `.dark` in app.css, so a view never needs its own
    `dark:` variant.
--}}
@if(($sources ?? collect())->count() > 1)
    @php
        $currentTab = 'bg-primary text-primary-fg border-primary';
        $defaultTab = 'border-border text-muted hover:text-surface-fg hover:border-primary';
    @endphp

    <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-muted">Fax server:</span>

        @foreach($sources as $source)
            @php($isCurrent = $source->key === ($sourceKey ?? ''))

            <a href="{{ request()->url() }}?source={{ urlencode($source->key) }}"
               class="rounded-md border px-3 py-1 font-medium transition {{ $isCurrent ? $currentTab : $defaultTab }}"
               @if($isCurrent) aria-current="page" @endif>
                {{ $source->name }}
            </a>
        @endforeach
    </div>
@endif
