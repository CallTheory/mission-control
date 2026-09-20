<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The window of switch data a dashboard widget reports on.
 *
 * Stored per-user on users.dashboard_timeframe. Historically only three values
 * existed -- '' (24 hours), 'lastHour' and 'sinceMidnight' -- and '' is still
 * on rows in the wild, so from() must never be used directly on the column;
 * fromStored() maps the legacy empty string onto Last24Hours.
 *
 * Every range is expressed in the *switch* timezone (System Settings ->
 * switch_data_timezone), because that is the wall clock the Amtelco tables are
 * written in. Converting to the viewer's timezone before formatting would shift
 * the query window off the data.
 */
enum DashboardTimeframe: string
{
    case Last15Minutes = 'last15Minutes';
    case Last30Minutes = 'last30Minutes';
    case LastHour = 'lastHour';
    case Last4Hours = 'last4Hours';
    case Last8Hours = 'last8Hours';
    case Last12Hours = 'last12Hours';
    case Last24Hours = 'last24Hours';
    case Last7Days = 'last7Days';
    case Last30Days = 'last30Days';
    case SinceMidnight = 'sinceMidnight';
    case Yesterday = 'yesterday';
    case ThisWeek = 'thisWeek';
    case ThisMonth = 'thisMonth';
    case LastMonth = 'lastMonth';

    /** What an unset (or unrecognised) preference means. */
    public const DEFAULT = self::Last24Hours;

    /**
     * Resolve the value persisted on the user, tolerating null, the legacy ''
     * and anything a stale form posted.
     */
    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DEFAULT;
    }

    public function label(): string
    {
        return match ($this) {
            self::Last15Minutes => 'Last 15 Minutes',
            self::Last30Minutes => 'Last 30 Minutes',
            self::LastHour => 'Last Hour',
            self::Last4Hours => 'Last 4 Hours',
            self::Last8Hours => 'Last 8 Hours',
            self::Last12Hours => 'Last 12 Hours',
            self::Last24Hours => 'Last 24 Hours',
            self::Last7Days => 'Last 7 Days',
            self::Last30Days => 'Last 30 Days',
            self::SinceMidnight => 'Today (Since Midnight)',
            self::Yesterday => 'Yesterday',
            self::ThisWeek => 'This Week',
            self::ThisMonth => 'This Month',
            self::LastMonth => 'Last Month',
        };
    }

    /**
     * Rolling windows end at "now"; calendar windows snap to day/week/month
     * boundaries. Only used to group the <select>.
     */
    public function group(): string
    {
        return match ($this) {
            self::SinceMidnight, self::Yesterday, self::ThisWeek,
            self::ThisMonth, self::LastMonth => 'Calendar',
            default => 'Rolling',
        };
    }

    /**
     * Start and end of the window, in the given (switch) timezone.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function range(string $timezone = 'UTC'): array
    {
        $now = CarbonImmutable::now($timezone);

        return match ($this) {
            self::Last15Minutes => [$now->subMinutes(15), $now],
            self::Last30Minutes => [$now->subMinutes(30), $now],
            self::LastHour => [$now->subHour(), $now],
            self::Last4Hours => [$now->subHours(4), $now],
            self::Last8Hours => [$now->subHours(8), $now],
            self::Last12Hours => [$now->subHours(12), $now],
            self::Last24Hours => [$now->subHours(24), $now],
            self::Last7Days => [$now->subDays(7), $now],
            self::Last30Days => [$now->subDays(30), $now],
            self::SinceMidnight => [$now->startOfDay(), $now],
            self::Yesterday => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            self::ThisWeek => [$now->startOfWeek(), $now],
            self::ThisMonth => [$now->startOfMonth(), $now],
            self::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
        };
    }

    /**
     * The same range pre-formatted for the stats query builders.
     *
     * @return array{string, string}
     */
    public function queryRange(string $timezone = 'UTC'): array
    {
        [$start, $end] = $this->range($timezone);

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /**
     * @return array<string, string> value => label, for validation and selects
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }

    /**
     * @return array<string, array<string, string>> group => (value => label)
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$case->group()][$case->value] = $case->label();
        }

        return $grouped;
    }
}
