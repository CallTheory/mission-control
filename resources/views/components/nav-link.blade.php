@props(['active'])

@php
$classes = ($active ?? false)
            ? 'transform transition duration-700 ease-in-out inline-flex items-center px-1 pt-1 border-b-2  border-gray-300 text-sm font-medium leading-5 text-gray-900   focus:outline-hidden transition'
            : 'transform transition duration-700 ease-in-out inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700  0  focus:outline-hidden focus:text-gray-700 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
