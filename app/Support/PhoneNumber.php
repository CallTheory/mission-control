<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical phone number handling for anything that keys a lookup on a number.
 *
 * Numbers reach us written every way a human writes them — `+1 (555) 123-4567`,
 * `555-123-4567`, `5551234567`, and out of a `.fs` file with a trailing `;`. Anything
 * that maps a number to a routing decision has to agree on one spelling or the map
 * quietly misses.
 *
 * Extracted from EnterpriseHost, which has keyed its SMS carrier overrides this way since
 * the WCTP gateway shipped; fax provider pins need the identical rule, and two copies of
 * it would eventually disagree.
 */
class PhoneNumber
{
    /**
     * Digits only, with the North American country code filled in, so numbers written
     * `+1 (555) 123-4567` and `5551234567` compare equal and key the same entry.
     */
    public static function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return $digits;
    }
}
