<?php

declare(strict_types=1);

namespace App\Services\Faxing;

/**
 * Builds the `ShouldBeUnique` identifier for the jobs that act on one `.fs` file.
 *
 * Two constraints meet here.
 *
 * A `.fs` filename (IS20.fs) is a short per-server Intelligent Series sequence, so it is
 * unique only within one spool source. Two sources would otherwise share a lock, and the
 * loser is never dispatched at all — PendingDispatch::__destruct drops it silently, so one
 * server's fax rots in tosend/ while its pending_faxes row says delivered.
 *
 * But a job serialized before this shipped carries no source and releases its lock against
 * whatever uniqueId() returns *after* the deploy. If that changed shape, the release would
 * miss and the original lock would be held for the full uniqueFor window — 7800 seconds
 * for SendFaxRingCentral — with that .fs undispatchable and nothing logged anywhere.
 *
 * So the sources that existed before routing (the ones named after their own provider)
 * keep the original bare key forever, and only genuinely new sources are namespaced.
 * Every existing install therefore sees byte-identical keys across the upgrade.
 */
class FaxLockKey
{
    /**
     * @param  string|null  $sourceKey  null for a payload written before sources existed
     */
    public static function for(?string $sourceKey, string $provider, string $fsFileName): string
    {
        $sourceKey ??= $provider;

        return $sourceKey === $provider ? $fsFileName : "{$sourceKey}:{$fsFileName}";
    }
}
