@props(['nowrap' => true, 'muted' => false])

<td {{ $attributes->merge(['class' => 'px-6 py-4 '.($nowrap ? 'whitespace-nowrap ' : '').'text-sm '.($muted ? 'text-muted' : 'text-surface-fg')]) }}>
    {{ $slot }}
</td>
