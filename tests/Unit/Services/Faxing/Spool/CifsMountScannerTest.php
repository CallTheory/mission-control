<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Faxing\Spool;

use App\Services\Faxing\Spool\Mounts\CifsMountScanner;
use Tests\TestCase;

/**
 * Pure string-to-struct parsing against real /proc/mounts lines, so the backfill can be
 * trusted without a mounted share to try it on.
 */
class CifsMountScannerTest extends TestCase
{
    private CifsMountScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scanner = new CifsMountScanner;
    }

    public function test_it_parses_a_typical_cifs_mount(): void
    {
        $mount = $this->scanner->parse(
            '//isserver/mfax /var/www/mc/storage/app/mfax cifs '
            .'rw,relatime,vers=3.1.1,cache=strict,username=svcfax,domain=CORP,uid=33,forceuid,gid=33,'
            .'forcegid,addr=10.0.0.5,file_mode=0664,dir_mode=0775,soft,nounix,serverino 0 0'
        );

        $this->assertNotNull($mount);
        $this->assertSame('/var/www/mc/storage/app/mfax', $mount->mountPoint);
        $this->assertSame('isserver', $mount->host);
        $this->assertSame('mfax', $mount->share);
        $this->assertSame('10.0.0.5', $mount->address);
        $this->assertSame('svcfax', $mount->username);
        $this->assertSame('CORP', $mount->domain);
        $this->assertSame('3.1.1', $mount->version);
        $this->assertTrue($mount->soft);
        $this->assertTrue($mount->isMigratable());
    }

    public function test_a_hard_mount_is_reported_as_such(): void
    {
        // Worth surfacing: on a hard mount a dead server blocks reads in uninterruptible
        // sleep, where no PHP timeout can reach them.
        $mount = $this->scanner->parse('//isserver/mfax /srv/app/mfax cifs rw,vers=3.0,username=a,addr=10.0.0.5 0 0');

        $this->assertFalse($mount->soft);
    }

    public function test_a_kerberos_mount_cannot_be_migrated(): void
    {
        // No username at all, and a ticket rather than a password.
        $mount = $this->scanner->parse('//isserver/mfax /srv/app/mfax cifs rw,sec=krb5,addr=10.0.0.5 0 0');

        $this->assertNull($mount->username);
        $this->assertTrue($mount->kerberos);
        $this->assertFalse($mount->isMigratable());
    }

    public function test_it_unescapes_a_mount_point_containing_a_space(): void
    {
        $mount = $this->scanner->parse('//isserver/fax\040share /srv/app/fax\040dir cifs rw,addr=10.0.0.5 0 0');

        $this->assertSame('/srv/app/fax dir', $mount->mountPoint);
        $this->assertSame('fax share', $mount->share);
    }

    public function test_it_ignores_lines_that_are_not_cifs(): void
    {
        $this->assertNull($this->scanner->parse('/dev/sda1 / ext4 rw,relatime 0 0'));
        $this->assertNull($this->scanner->parse('proc /proc proc rw,nosuid 0 0'));
        $this->assertNull($this->scanner->parse(''));
    }

    public function test_it_only_returns_mounts_under_the_requested_directory(): void
    {
        $procMounts = storage_path('framework/testing/mounts-'.uniqid());

        file_put_contents($procMounts, implode("\n", [
            '/dev/sda1 / ext4 rw,relatime 0 0',
            '//isserver/mfax '.storage_path('app/mfax').' cifs rw,username=a,addr=10.0.0.5,soft 0 0',
            // Elsewhere on the box: not ours to touch.
            '//other/share /mnt/elsewhere cifs rw,username=b,addr=10.0.0.9 0 0',
        ]));

        try {
            $mounts = (new CifsMountScanner($procMounts))->under(storage_path('app'));

            $this->assertCount(1, $mounts);
            $this->assertSame('mfax', $mounts[0]->share);
        } finally {
            @unlink($procMounts);
        }
    }

    public function test_a_missing_proc_mounts_yields_nothing_rather_than_failing(): void
    {
        $this->assertSame([], (new CifsMountScanner('/nonexistent/proc/mounts'))->under('/srv'));
    }
}
