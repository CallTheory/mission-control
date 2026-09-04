<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Guards the semantic-token design system.
 *
 * Dark mode works by redeclaring the tokens under `.dark` in resources/css/app.css,
 * which only reaches a component if that component consumes the token rather than a
 * raw palette step. A single `bg-white` or `text-gray-500` slipping back into a view
 * is invisible in light mode and renders unreadably in dark mode, so it is cheaper to
 * fail the build than to catch it by eye.
 */
class DesignSystemTest extends TestCase
{
    /**
     * Emails are excluded: they are delivered without the application stylesheet, so
     * they legitimately use inline styles and literal colours.
     */
    private const EXCLUDED_DIRS = ['emails'];

    private const PALETTES = 'gray|slate|zinc|neutral|stone|indigo|violet|purple|blue|sky|cyan|teal|emerald|green|lime|yellow|amber|orange|red|rose|pink|fuchsia';

    private const PROPERTIES = 'bg|text|border|divide|ring|from|to|via|placeholder|fill|stroke|accent|outline|shadow|caret';

    /**
     * @return array<int, SplFileInfo>
     */
    private function bladeFiles(): array
    {
        $root = resource_path('views');
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            foreach (self::EXCLUDED_DIRS as $excluded) {
                if (str_starts_with($relative, $excluded.'/')) {
                    continue 2;
                }
            }

            $files[$relative] = $file;
        }

        ksort($files);

        return array_values($files);
    }

    #[Test]
    public function blade_views_use_semantic_tokens_rather_than_raw_palette_classes(): void
    {
        $pattern = '/(?<![\w:-])(?:[a-z][a-z0-9-]*:)*(?:'.self::PROPERTIES.')-(?:'
            .self::PALETTES.')(?:-\d{2,3})?(?![\w-])/';

        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches)) {
                $relative = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
                $offenders[$relative] = array_values(array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders, $this->explain(
            'Blade views must consume semantic tokens (bg-surface, text-muted, border-border, '
            .'bg-primary, ...) instead of raw palette classes, or they will not adapt to dark mode.',
            $offenders
        ));
    }

    #[Test]
    public function blade_views_do_not_hardcode_black_or_white(): void
    {
        // text-white on a solid fill has a token (text-primary-fg, text-danger-fg, ...);
        // bg-white is bg-surface. Both are theme-dependent, so neither belongs in a view.
        $pattern = '/(?<![\w:-])(?:[a-z][a-z0-9-]*:)*(?:'.self::PROPERTIES.')-(?:white|black)(?![\w-])/';

        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches)) {
                $relative = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
                $offenders[$relative] = array_values(array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders, $this->explain(
            'Use the surface and foreground tokens instead of literal black/white.',
            $offenders
        ));
    }

    #[Test]
    public function views_do_not_need_dark_variants(): void
    {
        // The tokens carry dark mode. A `dark:` variant in a view means that element is
        // still painted with a raw palette step somewhere.
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if (preg_match_all('/(?<![\w:-])dark:[a-z0-9:-]+/', $contents, $matches)) {
                $relative = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
                $offenders[$relative] = array_values(array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders, $this->explain(
            'Dark mode is handled by redeclaring tokens under `.dark` in app.css; '
            .'a view should never need its own `dark:` variant.',
            $offenders
        ));
    }

    #[Test]
    public function every_token_declared_in_light_mode_has_a_dark_mode_value(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/@theme\s*\{(.+?)\n\}/s', $css, $themeBlock);
        preg_match('/\n\.dark\s*\{(.+?)\n\}/s', $css, $darkBlock);

        $this->assertNotEmpty($themeBlock, 'Could not locate the @theme block in app.css.');
        $this->assertNotEmpty($darkBlock, 'Could not locate the .dark block in app.css.');

        preg_match_all('/(--color-[a-z0-9-]+)\s*:/', $themeBlock[1], $light);
        preg_match_all('/(--color-[a-z0-9-]+)\s*:/', $darkBlock[1], $dark);

        // Fixed-by-design tokens: these read the same in both themes on purpose.
        $intentionallyShared = [];

        $missing = array_values(array_diff($light[1], $dark[1], $intentionallyShared));

        $this->assertSame([], $missing, $this->explain(
            'Every colour token defined in @theme must be redeclared under `.dark`, '
            .'otherwise components built on it keep their light-mode colour in dark mode.',
            $missing
        ));
    }

    /**
     * @param  array<mixed>  $offenders
     */
    private function explain(string $message, array $offenders): string
    {
        return $message."\n\n".json_encode($offenders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
