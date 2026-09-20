<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A user's appearance preference, stored on users.dark_mode.
 *
 * The column predates this enum and holds '' for light and 'dark' for dark, so
 * fromStored() has to absorb the empty string (and null, for rows written before
 * the column existed). System is new: nothing is stored for it beyond the literal
 * 'system', and the resolution to light or dark happens in the browser.
 */
enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Light;
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::System => 'System',
        };
    }

    /**
     * The class to stamp on <html> server-side.
     *
     * System renders nothing here on purpose: the server cannot know what the OS
     * is set to, so the inline script in the layout head resolves it before first
     * paint instead.
     */
    public function htmlClass(): string
    {
        return $this === self::Dark ? 'dark' : '';
    }

    /**
     * @return array<string, string> value => label
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
