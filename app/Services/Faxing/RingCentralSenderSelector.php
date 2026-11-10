<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Picks the extension the next fax goes out through.
 *
 * An extension is eligible when RingCentral has not locked it out and its last response
 * did not report zero requests remaining. Among eligible extensions the choice rotates
 * round-robin, so the load spreads across every extension's allowance. When none is
 * eligible the caller is told how long until the first one will be, and holds the fax
 * that long instead of sending it into a 429.
 *
 * Sends on one extension are serialized with a lock. Without it, several workers would
 * all read "3 remaining" and send together, and the state each recorded afterwards would
 * be stale before it was written. Serializing per extension is cheap: a fax upload takes
 * a second or two, far faster than any rate limit lets an extension send.
 */
class RingCentralSenderSelector
{
    /**
     * Longer than the sending supervisor's 120s timeout, so the lock outlives any send
     * that is still running but expires if the worker was killed mid-send.
     */
    public const SEND_LOCK_SECONDS = 150;

    /**
     * When every eligible extension is busy sending, how long to wait for one to come free
     * before handing the fax back to the queue for a moment.
     */
    public const DEFAULT_BUSY_WAIT_SECONDS = 10;

    public const BUSY_RETRY_SECONDS = 2;

    private const CURSOR_KEY = 'cloud-faxing:ringcentral:sender-cursor';

    public function __construct(
        private readonly RingCentralRateLimitState $state,
        private readonly int $busyWaitSeconds = self::DEFAULT_BUSY_WAIT_SECONDS,
    ) {}

    /**
     * A lease on an extension to send through now, or the number of seconds to wait.
     *
     * @param  list<RingCentralExtension>  $extensions
     */
    public function acquire(array $extensions, ?int $now = null): RingCentralSenderLease|int
    {
        if ($extensions === []) {
            throw new InvalidArgumentException('No RingCentral extensions to send through.');
        }

        $now ??= now()->getTimestamp();
        $earliest = null;
        $busy = null;

        foreach ($this->rotated($extensions) as $extension) {
            if (($at = $this->availableAt($extension, $now)) !== null) {
                $earliest = $earliest === null ? $at : min($earliest, $at);

                continue;
            }

            $lock = Cache::lock($this->lockKey($extension), self::SEND_LOCK_SECONDS);

            if (! $lock->get()) {
                $busy ??= $extension;

                continue;
            }

            if ($lease = $this->leaseIfStillAvailable($extension, $lock, $now, $earliest)) {
                return $lease;
            }
        }

        if ($busy !== null) {
            return $this->waitFor($busy, $earliest);
        }

        return max(1, (int) $earliest - $now);
    }

    /**
     * Seconds until any extension may send again, ignoring whether one is mid-send. Zero
     * when one can send now — after one extension is rate-limited, another may well have
     * requests to spare, and the fax should go straight back rather than wait out a
     * lockout that does not apply to it.
     *
     * @param  list<RingCentralExtension>  $extensions
     */
    public function secondsUntilAvailable(array $extensions, ?int $now = null): int
    {
        $now ??= now()->getTimestamp();
        $earliest = null;

        foreach ($extensions as $extension) {
            $at = $this->availableAt($extension, $now);

            if ($at === null) {
                return 0;
            }

            $earliest = $earliest === null ? $at : min($earliest, $at);
        }

        return $earliest === null ? 0 : max(0, $earliest - $now);
    }

    /**
     * Block briefly on an extension another worker is sending through. When its send
     * finishes the state it recorded decides whether this fax may go next.
     */
    private function waitFor(RingCentralExtension $extension, ?int $earliest): RingCentralSenderLease|int
    {
        $lock = Cache::lock($this->lockKey($extension), self::SEND_LOCK_SECONDS);

        try {
            $lock->block($this->busyWaitSeconds);
        } catch (LockTimeoutException) {
            return self::BUSY_RETRY_SECONDS;
        }

        $now = now()->getTimestamp();

        if ($lease = $this->leaseIfStillAvailable($extension, $lock, $now, $earliest)) {
            return $lease;
        }

        return max(1, (int) $earliest - $now);
    }

    /**
     * Re-check under the lock: the worker that held it may have just recorded that this
     * extension is out of requests.
     */
    private function leaseIfStillAvailable(RingCentralExtension $extension, Lock $lock, int $now, ?int &$earliest): ?RingCentralSenderLease
    {
        $at = $this->availableAt($extension, $now);

        if ($at !== null) {
            $lock->release();
            $earliest = $earliest === null ? $at : min($earliest, $at);

            return null;
        }

        if (($cap = $this->perExtensionCap()) !== null) {
            RateLimiter::hit($this->capKey($extension), 60);
        }

        return new RingCentralSenderLease($extension, $lock);
    }

    private function availableAt(RingCentralExtension $extension, int $now): ?int
    {
        $at = $this->state->availableAt($extension->key, $now);

        // The optional client-side ceiling. Off unless configured: the limit RingCentral
        // reports is the real one, and a guessed number here only adds waiting.
        if (($cap = $this->perExtensionCap()) !== null && RateLimiter::tooManyAttempts($this->capKey($extension), $cap)) {
            $capAt = $now + max(1, RateLimiter::availableIn($this->capKey($extension)));
            $at = $at === null ? $capAt : max($at, $capAt);
        }

        return $at;
    }

    /**
     * The extensions starting from the next round-robin position.
     *
     * @param  list<RingCentralExtension>  $extensions
     * @return list<RingCentralExtension>
     */
    private function rotated(array $extensions): array
    {
        $count = count($extensions);

        if ($count === 1) {
            return $extensions;
        }

        $start = ((int) Cache::increment(self::CURSOR_KEY)) % $count;

        return [...array_slice($extensions, $start), ...array_slice($extensions, 0, $start)];
    }

    private function perExtensionCap(): ?int
    {
        $cap = config('services.fax.ringcentral.sends_per_minute');

        return is_numeric($cap) && (int) $cap > 0 ? (int) $cap : null;
    }

    private function capKey(RingCentralExtension $extension): string
    {
        return 'cloud-faxing:ringcentral:cap:'.$extension->key;
    }

    private function lockKey(RingCentralExtension $extension): string
    {
        return 'cloud-faxing:ringcentral:send:'.$extension->key;
    }
}
