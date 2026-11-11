{{--
    A link that opens a Filament action and shows it is working until the action's modal
    arrives.

    Some actions fetch from a provider before their modal can render (Details asks
    RingCentral for its call log, which can take several seconds). A bare wire:click gave
    no sign anything was happening, so people clicked again and queued more requests.
    This disables the link and shows a spinner from the click until the server answers.

    $action    the action name, e.g. 'faxAttempts'
    $arguments the action's arguments; encoded here, so pass plain PHP values
    $variant   'link' (text link) or 'icon' (an icon in running text)
--}}
@props([
    'action',
    'arguments' => [],
    'loadingLabel' => __('Loading…'),
    'variant' => 'link',
])

<button
    type="button"
    x-data="{ loading: false }"
    x-on:click="
        if (loading) return;
        loading = true;
        $wire.mountAction(@js($action), @js($arguments)).finally(() => loading = false);
    "
    x-bind:disabled="loading"
    x-bind:aria-busy="loading"
    {{ $attributes->class([
        'inline-flex items-center gap-1 disabled:cursor-wait disabled:text-muted disabled:no-underline',
        'font-medium text-primary hover:underline' => $variant === 'link',
        'text-surface-fg hover:text-primary' => $variant === 'icon',
    ]) }}
>
    <svg x-show="loading" style="display: none" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span x-show="loading" style="display: none">{{ $loadingLabel }}</span>
    <span x-show="! loading">{{ $slot }}</span>
</button>
