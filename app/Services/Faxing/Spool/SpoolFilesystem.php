<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use App\Services\Faxing\Spool\Exceptions\SpoolUnavailable;

/**
 * One spool source's folders, whether they are a directory on this host or a share on an
 * Intelligent Series server.
 *
 * Addressed as (folder, leaf name) and never as a path. That single choice is what makes
 * a remote driver possible: containment becomes one validation in SpoolName rather than a
 * realpath check with no remote equivalent, and a move between folders is a first-class
 * operation rather than two paths that may not be on the same volume.
 */
interface SpoolFilesystem
{
    /**
     * Every file in a folder, with its size and modification time.
     *
     * Metadata comes back with the listing because fetching it per file would be two
     * extra round trips each over SMB. Returns an empty array for a folder that does not
     * exist; throws only when the source itself could not be reached, so an idle server
     * and an unreachable one are distinguishable — a standby Intelligent Series server
     * legitimately has an empty tosend/.
     *
     * @return array<int, SpoolFile>
     *
     * @throws SpoolUnavailable
     */
    public function list(string $folder): array;

    /**
     * File contents, or null when it is not there.
     *
     * @throws SpoolUnavailable
     */
    public function read(string $folder, string $name): ?string;

    /**
     * @throws SpoolUnavailable
     */
    public function write(string $folder, string $name, string $contents): void;

    /**
     * False when the file was already gone, which is the common case for a phantom the
     * fax service cleaned up between the page rendering and the click.
     *
     * @throws SpoolUnavailable
     */
    public function delete(string $folder, string $name): bool;

    /**
     * False when the source file is missing. Used for quarantining an invalid `.fs`.
     *
     * @throws SpoolUnavailable
     */
    public function move(string $fromFolder, string $fromName, string $toFolder, string $toName): bool;

    /**
     * Make sure a folder exists, where the driver is able to.
     */
    public function ensureFolder(string $folder): void;

    /**
     * Test the source. Never throws — a probe's job is to report a failure, not raise one,
     * because it is called from an admin screen and from the health check.
     */
    public function probe(): SpoolProbeResult;
}
