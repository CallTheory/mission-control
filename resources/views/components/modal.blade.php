@props(['formAction' => false])

<div class=" mt-25 min-h-full">
    @if($formAction)
        <form wire:submit="{{ $formAction }}">
    @endif

    <div class="bg-surface p-4 sm:px-6 sm:py-4 border-b border-border-soft ">
        @if(isset($title))
            <h3 class="text-lg leading-6 font-medium text-surface-fg ">
                {{ $title }}
            </h3>
        @endif
    </div>

    <div class="bg-surface px-4 sm:p-6">
        <div class="space-y-6">
            {{ $content }}
        </div>
    </div>

    <div class="bg-surface px-4 pb-5 sm:px-4 sm:flex bottom-0">
        {{ $buttons }}
    </div>

    @if($formAction)
        </form>
    @endif
</div>
