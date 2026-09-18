<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which Graph collection a credential was found in.
 *
 * Service principals can carry credentials that never appear under
 * /applications -- SAML signing certificates and some third-party apps -- so
 * both are swept, and the row says which one it came from.
 */
enum AzureCredentialSource: string
{
    case Application = 'application';
    case ServicePrincipal = 'servicePrincipal';

    public function label(): string
    {
        return match ($this) {
            self::Application => 'App registration',
            self::ServicePrincipal => 'Service principal',
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
