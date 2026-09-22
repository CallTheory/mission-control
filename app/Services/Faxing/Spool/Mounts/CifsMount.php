<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool\Mounts;

/**
 * A kernel CIFS mount discovered under the storage directory.
 *
 * Everything here except the password is recoverable from /proc/mounts. The password was
 * consumed by mount.cifs and never appears there, nor does the path of any credentials
 * file — only its effect survives. That single gap is why the migration needs one
 * deliberate action from an admin rather than being fully automatic.
 */
final readonly class CifsMount
{
    /**
     * @param  string|null  $address  the resolved IP, which is worth keeping: connecting
     *                                by name means a DNS lookup that fsockopen's timeout
     *                                does not cover, so a dead resolver becomes its own
     *                                unbounded block.
     */
    public function __construct(
        public string $mountPoint,
        public string $host,
        public string $share,
        public ?string $address = null,
        public ?string $username = null,
        public ?string $domain = null,
        public ?string $version = null,
        public bool $soft = false,
        public bool $kerberos = false,
    ) {}

    /**
     * Whether this mount can be migrated to stored credentials at all.
     *
     * A sec=krb5 mount carries no username and authenticates with a ticket; moving it to
     * password auth is a different problem, so it is reported rather than converted.
     */
    public function isMigratable(): bool
    {
        return ! $this->kerberos;
    }
}
