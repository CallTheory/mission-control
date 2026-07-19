@props(['padding' => 'p-6', 'title' => null])

<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-lg shadow']) }}>
    @isset($header)
        <div class="px-6 py-4 border-b border-border">{{ $header }}</div>
    @elseif($title)
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-lg font-medium text-surface-fg">{{ $title }}</h3>
        </div>
    @endisset

    <div class="{{ $padding }}">{{ $slot }}</div>

    @isset($footer)
        <div class="px-6 py-4 border-t border-border bg-surface-2">{{ $footer }}</div>
    @endisset
</div>
