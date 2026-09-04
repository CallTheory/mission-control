@props(['active'])

@php
$classes = ($active ?? false)
            ? 'transform transition duration-700 ease-in-out inline-flex items-center px-1 pt-1 border-b-2 border-border text-sm font-medium leading-5 text-surface-fg focus:outline-hidden transition'
            : 'transform transition duration-700 ease-in-out inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-muted hover:text-surface-fg-soft 0 focus:outline-hidden focus:text-surface-fg-soft transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
