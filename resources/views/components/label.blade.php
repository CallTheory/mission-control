@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-surface-fg transform transition duration-700']) }}>
    {{ $value ?? $slot }}
</label>
