<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The SMS carriers the WCTP gateway can relay through.
 *
 * A carrier is chosen per phone number (see EnterpriseHost::providerForNumber),
 * because a DID belongs to exactly one carrier -- a host can hold Twilio and
 * Bandwidth numbers at the same time and each has to go out the door it came in.
 * Numbers with no explicit carrier fall back to the system default
 * (`data_sources.sms_default_provider`).
 */
enum SmsProvider: string
{
    case Twilio = 'twilio';
    case Bandwidth = 'bandwidth';
    case Commio = 'commio';

    public function label(): string
    {
        return match ($this) {
            self::Twilio => 'Twilio',
            self::Bandwidth => 'Bandwidth',
            self::Commio => 'Commio',
        };
    }

    /**
     * The carrier assumed when nothing has been chosen anywhere. Twilio, because
     * it is the provider the gateway shipped with.
     */
    public static function fallback(): self
    {
        return self::Twilio;
    }

    public static function tryFromKey(?string $key): ?self
    {
        return $key === null || $key === '' ? null : self::tryFrom($key);
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
