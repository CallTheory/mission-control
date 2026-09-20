{{-- Segmented light/dark/system switch. Alpine applies the theme immediately and
     $wire.setTheme persists it; the server round trip never has to come back for
     the UI to look right. --}}
<div class="px-4 py-2" x-data="{
        theme: @js($theme),
        select(value) {
            this.theme = value;
            window.missionControlTheme?.apply(value);
            $wire.setTheme(value);
        },
    }">
    <div class="mb-1 text-xs text-muted">{{ __('Appearance') }}</div>

    <div class="flex rounded-md border border-border overflow-hidden" role="group" aria-label="{{ __('Appearance') }}">
        @foreach($options as $option)
            <button type="button"
                    @click.prevent="select(@js($option->value))"
                    :aria-pressed="theme === @js($option->value) ? 'true' : 'false'"
                    :class="theme === @js($option->value)
                        ? 'bg-primary text-primary-fg'
                        : 'bg-surface text-surface-fg-soft hover:bg-surface-2'"
                    class="flex-1 cursor-pointer px-2 py-1 text-xs transition focus:outline-hidden">
                {{ __($option->label()) }}
            </button>
        @endforeach
    </div>
</div>
