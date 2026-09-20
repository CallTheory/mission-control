{{-- Segmented light/dark/system switch. Alpine applies the theme immediately and
     $wire.setTheme persists it; the server round trip never has to come back for
     the UI to look right. --}}
<div class="py-3" x-data="{
        theme: @js($theme),
        select(value) {
            this.theme = value;
            window.missionControlTheme?.apply(value);
            $wire.setTheme(value);
        },
    }">
    <div class="mb-2 px-4 text-xs text-muted">{{ __('Appearance') }}</div>

    {{-- Full-bleed rather than inset: the menu is only w-48, so the menu's own
         px-4 gutter left three segments about 52px each and "System" barely fit.

         No border-y on the bar. It had one, and with a menu-break directly above
         and below that stacked four horizontal rules inside about forty pixels --
         the bar's lower border landing all but on top of the separator under it.
         The section's padding and the two separators already bound this group, so
         the segments only need the dividers between them. --}}
    <div class="flex w-full divide-x divide-border"
         role="group"
         aria-label="{{ __('Appearance') }}">
        @foreach($options as $option)
            <button type="button"
                    @click.prevent="select(@js($option->value))"
                    :aria-pressed="theme === @js($option->value) ? 'true' : 'false'"
                    {{-- Inactive segments are surface-3, not surface-2. They began
                         as bg-surface -- the menu panel's own token -- so they had
                         no edge at all. surface-2 fixes dark mode but in light mode
                         it is gray-50 against a white panel, 1.05:1, still invisible;
                         surface-3 reads in both. Hover tints toward primary so it
                         does not have to find yet another neutral step. --}}
                    :class="theme === @js($option->value)
                        ? 'bg-primary text-primary-fg'
                        : 'bg-surface-3 text-surface-fg-soft hover:bg-primary-soft hover:text-primary-soft-fg'"
                    class="flex-1 cursor-pointer whitespace-nowrap px-1 py-1.5 text-xs transition focus:outline-hidden">
                {{ __($option->label()) }}
            </button>
        @endforeach
    </div>
</div>
