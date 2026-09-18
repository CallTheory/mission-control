<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How urgent an Entra credential's expiry is.
 *
 * Derived from days remaining at read time, never stored -- see the
 * azure_credentials migration for why.
 */
enum AzureCredentialStatus: string
{
    case Expired = 'expired';
    case Critical = 'critical';
    case Warning = 'warning';
    case Healthy = 'healthy';

    /** Days remaining at or below which a credential is Critical. */
    public const CRITICAL_DAYS = 14;

    /** Days remaining at or below which a credential is a Warning. */
    public const WARNING_DAYS = 30;

    public static function fromDaysRemaining(int $days): self
    {
        return match (true) {
            $days < 0 => self::Expired,
            $days <= self::CRITICAL_DAYS => self::Critical,
            $days <= self::WARNING_DAYS => self::Warning,
            default => self::Healthy,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Expired => 'Expired',
            self::Critical => 'Critical',
            self::Warning => 'Warning',
            self::Healthy => 'Healthy',
        };
    }

    /**
     * Filament badge colour. Expired and Critical share danger deliberately: an
     * expired credential is not less urgent than one expiring tomorrow.
     */
    public function color(): string
    {
        return match ($this) {
            self::Expired, self::Critical => 'danger',
            self::Warning => 'warning',
            self::Healthy => 'success',
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
