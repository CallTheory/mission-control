@props(['current' => ''])

{{--
    The WCTP section's sub-navigation. These were tabs on a single screen; each is
    now its own page, so they are links, and each appears only if the viewer can
    open it.
--}}
@php
    $links = [];

    if (auth()->user()?->hasCapability(\App\Enums\Capability::WctpManage)) {
        $links['gateway'] = ['label' => 'Gateway', 'url' => route('system.wctp.gateway')];
        $links['carriers'] = ['label' => 'Carriers', 'url' => route('system.wctp.carriers')];
        $links['enterprise-hosts'] = ['label' => 'Enterprise Hosts', 'url' => route('system.wctp.enterprise-hosts')];
    }

    if (auth()->user()?->hasCapability(\App\Enums\Capability::WctpMessages)) {
        $links['messages'] = ['label' => 'Messages', 'url' => route('system.wctp.messages')];
    }
@endphp

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-border-soft pb-2 mb-4" aria-label="WCTP Gateway">
        <a href="{{ route('system.wctp') }}" class="text-sm font-medium text-muted hover:text-surface-fg">
            &larr; WCTP Gateway
        </a>

        @foreach ($links as $key => $link)
            <a href="{{ $link['url'] }}"
               @class([
                   'text-sm font-medium whitespace-nowrap',
                   'text-primary' => $current === $key,
                   'text-surface-fg-soft hover:text-surface-fg' => $current !== $key,
               ])
               @if($current === $key) aria-current="page" @endif>
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
