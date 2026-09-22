<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use Icewind\SMB\System;

/**
 * Wraps smbclient in `timeout` so a wedged session cannot block PHP indefinitely.
 *
 * icewind/smb drives one long-lived interactive smbclient over a pipe and reads replies
 * with a blocking stream_get_line(). That is efficient — N operations share one SMB
 * session rather than spawning a process each — but it has no wall-clock bound: if
 * smbclient stops answering without exiting, PHP blocks forever, which is the very
 * failure the app-level driver exists to avoid. The library exposes no timeout for it.
 *
 * Share::getConnection() interpolates getSmbclientPath() into a shell command string, so
 * returning a `timeout ... smbclient` prefix produces
 *
 *     exec stdbuf -o0 /usr/bin/timeout -k 5 40 /usr/bin/smbclient -t 15 ...
 *
 * When the budget expires smbclient is killed, the pipe reaches EOF, and the blocking
 * read *returns* instead of hanging. stdbuf's LD_PRELOAD is inherited through timeout, so
 * buffering still behaves.
 *
 * This depends on the shape of a sprintf() inside the library, so composer.json pins
 * icewind/smb to ^3.8 and GuardedSystemTest asserts the constructed command.
 */
class GuardedSystem extends System
{
    public function __construct(private readonly int $sessionSeconds = 40) {}

    public function getSmbclientPath(): ?string
    {
        return self::wrap(parent::getSmbclientPath(), $this->sessionSeconds, self::timeoutBinary());
    }

    /**
     * Prefix a smbclient path with a wall-clock guard.
     *
     * Separated from getSmbclientPath() so the command this produces can be asserted
     * without smbclient being installed — the CI image has no Samba client, and this is
     * the piece that must not silently stop wrapping if the library changes shape.
     */
    public static function wrap(?string $smbclient, int $sessionSeconds, ?string $timeoutBinary): ?string
    {
        if ($smbclient === null) {
            return null;
        }

        if ($timeoutBinary === null) {
            // Without coreutils' timeout there is no wall-clock guard available. Still
            // usable — smbclient's own -t bounds each SMB request — so this degrades
            // rather than refusing to run.
            return $smbclient;
        }

        // -k gives smbclient a grace period to exit on TERM before it is killed outright.
        return "{$timeoutBinary} -k 5 {$sessionSeconds} {$smbclient}";
    }

    public static function timeoutBinary(): ?string
    {
        foreach (['/usr/bin/timeout', '/bin/timeout'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
