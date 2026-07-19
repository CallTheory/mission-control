@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-border bg-surface text-surface-fg focus:border-primary focus:ring focus:ring-primary/30 rounded-md shadow']) !!}>
