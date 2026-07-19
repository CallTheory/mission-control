@props([
    'wireModel' => 'search',
    'label' => 'Search',
    'placeholder' => 'Search...',
    'live' => true,
])

<div>
    @if($label)
        <x-label :value="$label" />
    @endif

    <input type="text" placeholder="{{ $placeholder }}"
        @if($wireModel) wire:model{{ $live ? '.live' : '' }}="{{ $wireModel }}" @endif
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-border bg-surface text-surface-fg text-sm shadow-sm focus:border-primary focus:ring focus:ring-primary/30']) }} />
</div>
