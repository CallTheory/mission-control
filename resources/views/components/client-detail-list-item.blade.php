@props(['label' => '', 'details' => '', 'link' => ''])<div class="px-2 py-3 sm:grid sm:grid-cols-3 lg:grid-cols-2 sm:gap-4 sm:px-0">
    <dt class="text-sm font-semibold leading-6 text-surface-fg">{{ $label }}</dt>
    <dd class="mt-1 text-sm leading-6 text-surface-fg-soft sm:col-span-2 lg:col-span-1 sm:mt-0">
        @if(strlen($link))
            <a href="{{ $link }}" class="font-semibold hover:underline text-primary">{{ $details }}</a>
        @else
            {{ $details }}
        @endif
    </dd>
</div>
