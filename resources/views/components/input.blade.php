@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ']) !!}>
