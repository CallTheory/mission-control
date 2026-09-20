{{-- Resolves the "System" appearance preference before first paint.

     The server stamps `dark` on <html> for an explicit dark preference, but it
     cannot know the OS setting, so System is left bare and settled here. Kept
     inline and first in <head> deliberately: loading it as a module would run
     after the stylesheet and flash the wrong theme. --}}
<script>
    (function () {
        var preference = @json(\App\Enums\ThemePreference::fromStored(auth()->user()?->dark_mode)->value);
        var query = window.matchMedia('(prefers-color-scheme: dark)');

        window.missionControlTheme = {
            preference: preference,
            apply: function (value) {
                this.preference = value;
                document.documentElement.classList.toggle(
                    'dark',
                    value === 'dark' || (value === 'system' && query.matches)
                );
            },
        };

        window.missionControlTheme.apply(preference);

        // Follow the OS while -- and only while -- the preference is System.
        query.addEventListener('change', function () {
            if (window.missionControlTheme.preference === 'system') {
                window.missionControlTheme.apply('system');
            }
        });
    })();
</script>
