<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeZone;

/**
 * Grouped timezone options for a Filament Select.
 *
 * The profile and System > User screens used to carry a hand-written <datalist>
 * of the US and Canadian zones, annotated with the names people here actually
 * say ("Central", "Mountain no DST"). Those annotations are the useful part and
 * are kept; the markup around them is not, and is replaced by a searchable
 * Select in the same style as System > Switch Data Timezone.
 *
 * The US and Canadian zones come first because they are what this application's
 * users pick in practice. Every remaining identifier follows under "All Time
 * Zones", so nothing is unreachable -- and no identifier appears twice, which
 * would leave the Select showing whichever label it matched first.
 */
class TimezoneOptions
{
    /**
     * Spoken names for the zones this call centre audience selects, keyed by
     * identifier. Anything absent simply shows its city.
     *
     * @var array<string, string>
     */
    private const NOTATIONS = [
        // United States
        'America/New_York' => 'Eastern (EST/EDT)',
        'America/Chicago' => 'Central',
        'America/Denver' => 'Mountain',
        'America/Phoenix' => 'Mountain no DST',
        'America/Los_Angeles' => 'Pacific',
        'America/Anchorage' => 'Alaska',
        'America/Adak' => 'Hawaii-Aleutian',
        'Pacific/Honolulu' => 'Hawaii no DST',

        // Canada
        'America/St_Johns' => 'Newfoundland',
        'America/Halifax' => 'Atlantic',
        'America/Blanc-Sablon' => 'Atlantic no DST',
        'America/Toronto' => 'Eastern',
        'America/Atikokan' => 'Eastern no DST',
        'America/Winnipeg' => 'Central',
        'America/Regina' => 'Central no DST',
        'America/Edmonton' => 'Mountain',
        'America/Creston' => 'Mountain no DST',
        'America/Vancouver' => 'Pacific',
    ];

    /**
     * @return array<string, array<string, string>> group heading => [identifier => label]
     */
    public static function grouped(): array
    {
        $us = self::forCountry('US');
        $canada = self::forCountry('CA');

        $claimed = ['UTC' => true] + $us + $canada;

        $rest = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $identifier) {
            if (! isset($claimed[$identifier])) {
                $rest[$identifier] = str_replace('_', ' ', $identifier);
            }
        }

        return [
            __('Coordinated Universal Time') => ['UTC' => 'UTC · GMT'],
            __('United States') => $us,
            __('Canada') => $canada,
            __('All Time Zones') => $rest,
        ];
    }

    /**
     * @return array<string, string> identifier => label
     */
    private static function forCountry(string $country): array
    {
        $options = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country) as $identifier) {
            $parts = explode('/', $identifier);

            // "America/Indiana/Knox" reads best as "Knox, Indiana"; a plain
            // "America/Chicago" is just "Chicago".
            $place = count($parts) > 2
                ? implode(', ', array_reverse(array_slice($parts, 1, 2)))
                : implode(' ', array_slice($parts, 1, 1));

            $label = str_replace('_', ' ', $place);

            if (isset(self::NOTATIONS[$identifier])) {
                $label .= ' · '.self::NOTATIONS[$identifier];
            }

            $options[$identifier] = $label;
        }

        return $options;
    }
}
