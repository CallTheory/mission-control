@props(['color' => 'gray', 'pill' => true])

@php
    $colors = [
        'gray' => 'bg-surface-2 text-surface-fg ',
        'green' => 'bg-success-soft text-success-soft-fg ',
        'red' => 'bg-danger-soft text-danger-soft-fg ',
        'yellow' => 'bg-warning-soft text-warning-soft-fg ',
        'blue' => 'bg-info-soft text-info-soft-fg ',
        'purple' => 'bg-accent-soft text-accent-soft-fg ',
    ];
    $shape = $pill ? 'rounded-full' : 'rounded';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 '.$shape.' text-xs font-medium '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
