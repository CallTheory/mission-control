@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700   transform transition duration-700']) }}>
    {{ $value ?? $slot }}
</label>
