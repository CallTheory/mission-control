{{--
    Fax server view filter.

    This changes which server's spool you are looking at. It is *not* a setting and there
    is deliberately no "primary" server to choose: Intelligent Series decides which of its
    fax services is active, Mission Control reads all of them, and whichever is producing
    files is the live one. Anything configurable — which providers are on, the default
    provider, failover, per-number pins, and the servers themselves — lives in
    System → Cloud Faxing.

    Rendered only when this provider is fed by more than one server, so the ordinary
    single-server install never sees it. Styled as a subordinate tab row rather than
    buttons, because filled pills next to the provider tabs read like a choice being
    saved.
--}}
@if(($sources ?? collect())->count() > 1)
    <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
        <span class="text-muted">Viewing spool for</span>

        <nav class="flex flex-wrap gap-x-4">
            @foreach($sources as $source)
                @php($isCurrent = $source->key === ($sourceKey ?? ''))

                <a href="{{ request()->url() }}?source={{ urlencode($source->key) }}"
                   class="border-b-2 pb-1 font-medium {{ $isCurrent
                       ? 'border-primary text-primary'
                       : 'border-transparent text-muted hover:border-border hover:text-surface-fg' }}"
                   @if($isCurrent) aria-current="page" @endif>
                    {{ $source->name }}
                </a>
            @endforeach
        </nav>
    </div>
@endif
