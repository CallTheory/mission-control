@props(['title' => null])

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        @if($title)
            <h2 class="text-2xl font-semibold text-surface-fg">{{ $title }}</h2>
        @endif
        @isset($subtitle)
            <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
        @endisset
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
