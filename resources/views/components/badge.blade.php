@props(['color' => 'gray', 'pill' => true])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    ];
    $shape = $pill ? 'rounded-full' : 'rounded';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 '.$shape.' text-xs font-medium '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
