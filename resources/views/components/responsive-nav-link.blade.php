@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block pl-3 pr-4 py-2 border-l-4 border-primary text-base font-medium text-primary-soft-fg bg-primary-soft focus:outline-hidden focus:text-primary-soft-fg focus:bg-primary-soft focus:border-primary transition'
            : 'block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-surface-fg-soft hover:text-surface-fg hover:bg-surface-2 hover:border-border focus:outline-hidden focus:text-surface-fg focus:bg-surface-2 focus:border-border transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
