<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Faxing\Spool;

use App\Services\Faxing\Spool\GuardedSystem;
use Tests\TestCase;

/**
 * icewind/smb reads smbclient's replies with a blocking call that has no timeout of its
 * own, so wrapping the binary in `timeout` is the only wall-clock guard there is. It works
 * by being interpolated into a shell command inside the library, which means a change in
 * the library's shape could silently stop it wrapping — hence composer's ^3.8 pin and
 * these assertions.
 */
class GuardedSystemTest extends TestCase
{
    public function test_it_wraps_smbclient_in_a_wall_clock_timeout(): void
    {
        $this->assertSame(
            '/usr/bin/timeout -k 5 40 /usr/bin/smbclient',
            GuardedSystem::wrap('/usr/bin/smbclient', 40, '/usr/bin/timeout'),
        );
    }

    public function test_it_degrades_rather_than_refusing_to_run_without_timeout(): void
    {
        // smbclient's own -t still bounds each SMB request, so this is usable — just
        // without the outer guard. The probe reports it as a warning.
        $this->assertSame(
            '/usr/bin/smbclient',
            GuardedSystem::wrap('/usr/bin/smbclient', 40, null),
        );
    }

    public function test_it_stays_null_when_smbclient_is_not_installed(): void
    {
        $this->assertNull(GuardedSystem::wrap(null, 40, '/usr/bin/timeout'));
    }

    public function test_the_wrapped_command_is_what_icewind_interpolates(): void
    {
        // Share::getConnection() builds: exec [stdbuf -o0 ]<smbclient path> -t <timeout>...
        // so the wrapper has to sit where the binary would, with its arguments in front
        // of smbclient's own.
        $wrapped = GuardedSystem::wrap('/usr/bin/smbclient', 40, '/usr/bin/timeout');
        $command = sprintf('exec %s%s -t %s', 'stdbuf -o0 ', $wrapped, 15);

        $this->assertSame('exec stdbuf -o0 /usr/bin/timeout -k 5 40 /usr/bin/smbclient -t 15', $command);
    }
}
