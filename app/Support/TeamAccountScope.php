<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * The invariant behind `teams.unrestricted_accounts`: a shared team is either scoped to
 * a list of accounts or billing codes, or it is explicitly marked as seeing every one.
 * It may not be neither.
 *
 * "Neither" is the state that made empty lists ambiguous -- indistinguishable from a
 * team nobody had configured -- and it is the state `CallAccess` now withholds call data
 * from. Blocking it at the point of saving keeps that denial from being a surprise
 * discovered later through a 403.
 *
 * Two forms edit the two lists separately, so both call this with the values the team
 * would end up holding, not just the ones they own.
 */
final class TeamAccountScope
{
    /**
     * @param  string  $field  the form field to hang the error on
     *
     * @throws ValidationException
     */
    public static function validate(
        ?string $allowedAccounts,
        ?string $allowedBilling,
        bool $unrestricted,
        string $field,
    ): void {
        if (self::isDecided($allowedAccounts, $allowedBilling, $unrestricted)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Enter at least one account or billing code, or tick "This team may '
                .'see every account" to say that no restriction is intended. A team with '
                .'neither is treated as unconfigured, and call data is withheld from it.',
        ]);
    }

    public static function isDecided(
        ?string $allowedAccounts,
        ?string $allowedBilling,
        bool $unrestricted,
    ): bool {
        return $unrestricted
            || trim((string) $allowedAccounts) !== ''
            || trim((string) $allowedBilling) !== '';
    }
}
