@props([
    'for' => null,
    'label' => null,
    'type' => 'text',
    'help' => null,
    'errorFor' => null,
    'colSpan' => 'col-span-6 sm:col-span-4',
])

{{-- Standard label + control + error + hint field cell. Forwards any extra
     attributes (wire:model, placeholder, required, disabled, ...) to the input.
     Pass a default slot to override the control entirely (e.g. a <select>). --}}
<div class="{{ $colSpan }} my-2">
    @if($label)
        <x-label :for="$for" :value="$label" />
    @endif

    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <x-input :id="$for" :type="$type" class="mt-1 block w-full" {{ $attributes }} />
    @endif

    @if($errorFor)
        <x-input-error :for="$errorFor" class="mt-2" />
    @endif

    @if($help)
        <p class="mt-1 text-sm text-muted">{{ $help }}</p>
    @endif
</div>
