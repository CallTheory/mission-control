<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Support\Str;

/**
 * The E.164 form RingCentral expects for a number taken from a .fs file, which may carry
 * Intelligent Series' outside-line prefix (9, or 91).
 */
final class RingCentralRecipient
{
    public static function normalize(string $phone): string
    {
        if (! Str::startsWith($phone, '+') && strlen($phone) === 10) {
            return "+1{$phone}";
        }

        if (Str::startsWith($phone, '9') && strlen($phone) === 11) {
            return '+1'.Str::after($phone, '9');
        }

        if (Str::startsWith($phone, '91') && strlen($phone) === 12) {
            return '+1'.Str::after($phone, '91');
        }

        if (! Str::startsWith($phone, '+')) {
            return "+{$phone}";
        }

        return $phone;
    }
}
