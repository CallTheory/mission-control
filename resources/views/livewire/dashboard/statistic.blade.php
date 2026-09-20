{{-- No timeframe argument: the component reads the viewer's saved preference,
     so changing it in the header updates every widget without a reload. --}}
<li wire:poll.5000ms.visible="update"
    wire:id="{{ uniqid('widget') }}"
    class="widget relative"
    style="min-height: 20rem;">
    <div class="px-4 py-8 min-h-full
            group block w-full aspect-w-10 aspect-h-7 rounded-lg focus-within:ring-2
            focus-within:ring-offset-2 focus-within:ring-offset-indigo-100 focus-within:ring-primary
            overflow-hidden border border-primary  shadow bg-surface  mx-auto">
        <div class="mx-auto align-content-center align-middle h-full h-100 group">
            <div class="h-full relative my-16 cursor-help" title="{{$title}} &middot; {{ $metric }} @if(strlen($tail)){{ $tail }}@endif" >
                <h3 class="text-2xl text-center font-semibold text-muted mx-auto 0 transform transition ease-in-out duration-700">
                    {{ $title }}
                </h3>
                <p class="mx-auto text-center text-surface-fg-soft text-9xl text-ellipsis transform transition ease-in-out duration-700">
                    <span>{{ number_format($metric, $rounding) }}</span>@if(strlen($tail))<span class="text-primary">{{ $tail }}</span>@endif
                </p>
                @if(strlen($desc))
                    <small class="block my-4 mx-auto text-center text-sm text-primary transform transition ease-in-out duration-700">
                        {{ $desc }}
                    </small>
                @endif
            </div>
        </div>
    </div>
</li>

