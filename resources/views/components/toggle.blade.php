@props([
    'wireModel' => null,
    'label' => null,
    'help' => null,
    'live' => false,
])

<label class="flex items-start gap-3 cursor-pointer">
    <x-checkbox class="mt-0.5"
        @if($wireModel) wire:model{{ $live ? '.live' : '' }}="{{ $wireModel }}" @endif
        {{ $attributes }} />
    <span>
        @if($label)
            <span class="block text-sm font-medium text-surface-fg">{{ $label }}</span>
        @endif
        @if($help)
            <span class="block text-sm text-muted">{{ $help }}</span>
        @endif
    </span>
</label>
