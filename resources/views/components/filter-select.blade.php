@props([
    'for' => null,
    'label' => null,
    'wireModel' => null,
    'live' => true,
    'options' => [],
    'placeholder' => 'All',
    'optionValue' => 'id',
    'optionLabel' => 'name',
])

<div>
    @if($label)
        <x-label :for="$for" :value="$label" />
    @endif

    <select id="{{ $for }}"
        @if($wireModel) wire:model{{ $live ? '.live' : '' }}="{{ $wireModel }}" @endif
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-border bg-surface text-surface-fg text-sm shadow-sm focus:border-primary focus:ring focus:ring-primary/30']) }}>
        @isset($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endisset
        @if($slot->isNotEmpty())
            {{ $slot }}
        @else
            @foreach($options as $option)
                @php
                    $val = is_array($option) ? ($option[$optionValue] ?? '') : (is_object($option) ? ($option->{$optionValue} ?? '') : $option);
                    $lbl = is_array($option) ? ($option[$optionLabel] ?? $val) : (is_object($option) ? ($option->{$optionLabel} ?? $val) : $option);
                @endphp
                <option value="{{ $val }}">{{ $lbl }}</option>
            @endforeach
        @endif
    </select>
</div>
