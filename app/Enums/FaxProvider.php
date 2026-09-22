<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The cloud fax providers Mission Control can submit through.
 *
 * Historically the provider was not a value at all — it was the name of the spool
 * directory Amtelco's fax service had been pointed at, so switching provider meant an IS
 * Supervisor change on the customer's server. Naming the providers here is the first step
 * to choosing one per fax instead (see FaxSpoolSource::$pinned_provider, and the routing
 * that replaces it).
 *
 * Mirrors SmsProvider, which solves the same shape for WCTP carriers.
 */
enum FaxProvider: string
{
    case Mfax = 'mfax';
    case RingCentral = 'ringcentral';

    public function label(): string
    {
        return match ($this) {
            self::Mfax => 'mFax',
            self::RingCentral => 'RingCentral',
        };
    }

    /**
     * The provider assumed when nothing has been chosen anywhere. mFax, because it is
     * the provider cloud faxing shipped with.
     */
    public static function fallback(): self
    {
        return self::Mfax;
    }

    public static function tryFromKey(?string $key): ?self
    {
        return $key === null || $key === '' ? null : self::tryFrom($key);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::cases(), 'value');
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
