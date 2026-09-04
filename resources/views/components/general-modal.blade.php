@props(['formAction' => false])

<div class="">
    @if($formAction)
        <form wire:submit="{{ $formAction }}">
            @endif

            <div class="bg-surface p-4 sm:px-6 sm:py-4">
                @if(isset($title))
                    <h3 class="text-lg leading-6 font-medium text-surface-fg ">
                        {{ $title }}
                    </h3>
                @endif
            </div>

            <div class="bg-surface px-2">
                <div class="space-y-6 mx-4">
                    {{ $content }}
                </div>
            </div>

            <div class="bg-surface mt-2 px-4 pb-5 sm:px-4 sm:flex">
                {{ $buttons }}
            </div>

            @if($formAction)
        </form>
    @endif
</div>
