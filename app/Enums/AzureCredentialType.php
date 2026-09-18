<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two kinds of credential an Entra app registration can hold.
 *
 * Graph returns them in separate collections on the same object --
 * `passwordCredentials` and `keyCredentials` -- which is the only real
 * difference at sweep time.
 */
enum AzureCredentialType: string
{
    case Secret = 'secret';
    case Certificate = 'certificate';

    public function label(): string
    {
        return match ($this) {
            self::Secret => 'Client secret',
            self::Certificate => 'Certificate',
        };
    }

    /**
     * @return array<string, string> keyed by value, for Filament selects
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
