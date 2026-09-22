<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Enums\FaxProvider;

/**
 * The outcome of routing one fax: which provider, why, and whether a failed submission
 * may be retried through a different one.
 *
 * The reason is carried through to pending_faxes because "why did this fax go out through
 * RingCentral" is the first question anyone asks once routing stops being visible in the
 * directory name.
 */
final readonly class FaxRoute
{
    public function __construct(
        public FaxProvider $provider,
        public string $reason,
        public bool $allowFailover,
    ) {}
}
