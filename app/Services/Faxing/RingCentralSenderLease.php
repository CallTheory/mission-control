<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Contracts\Cache\Lock;

/**
 * The right to send one fax through one extension, right now.
 *
 * Holding it means no other worker is sending on this extension, so the rate-limit state
 * read before sending is still accurate when the response comes back. Release it in a
 * finally block; the lock also expires on its own if the worker dies.
 */
final class RingCentralSenderLease
{
    private bool $released = false;

    public function __construct(
        public readonly RingCentralExtension $extension,
        private readonly Lock $lock,
    ) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        $this->lock->release();
    }
}
