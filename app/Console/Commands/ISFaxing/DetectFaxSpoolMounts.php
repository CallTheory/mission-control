<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Models\FaxSpoolSource;
use App\Services\Faxing\Spool\Mounts\CifsMount;
use App\Services\Faxing\Spool\Mounts\CifsMountScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Turns existing kernel CIFS mounts into spool sources Mission Control can manage itself.
 *
 * Read-only by default. With --persist it writes draft sources that stay on the local
 * driver, so the existing mount keeps serving every fax while an admin supplies the one
 * thing that cannot be recovered — the password — and tests the connection before
 * switching the source over. Nothing about faxing changes until they do.
 */
class DetectFaxSpoolMounts extends Command
{
    protected $signature = 'isfax:detect-mounts
        {--persist : Create draft spool sources for what is found}';

    protected $description = 'Find kernel CIFS mounts under storage/app that could become managed spool sources';

    public function handle(CifsMountScanner $scanner): int
    {
        $mounts = $scanner->under(storage_path('app'));

        if ($mounts === []) {
            $this->info('No CIFS mounts found under '.storage_path('app').'.');
            // Worth saying, because it is the usual explanation: in Sail or any container
            // the mount belongs to the host and is simply not visible from in here.
            $this->line('If Mission Control runs in a container, run this on the host instead —');
            $this->line('a mount made outside the container does not appear in its /proc/mounts.');

            return CommandStatus::SUCCESS;
        }

        $this->table(
            ['Mount point', 'Share', 'Address', 'Username', 'Domain', 'SMB', 'Soft', 'Migratable'],
            array_map(fn (CifsMount $m): array => [
                $m->mountPoint,
                "//{$m->host}/{$m->share}",
                $m->address ?? '—',
                $m->username ?? '—',
                $m->domain ?? '—',
                $m->version ?? '—',
                $m->soft ? 'yes' : 'NO',
                $m->isMigratable() ? 'yes' : 'no (Kerberos)',
            ], $mounts),
        );

        $this->newLine();
        $this->warn('The share password is never recorded in /proc/mounts and cannot be recovered here.');
        $this->line('It is in the mount command or the credentials file referenced by your fstab entry.');

        foreach ($mounts as $mount) {
            if (! $mount->soft) {
                $this->warn("{$mount->mountPoint} is a hard mount. If the server stops answering, reads on it "
                    .'block in uninterruptible sleep, where no PHP timeout can reach them. Add `soft` until '
                    .'this source is moved to the SMB driver.');
            }
        }

        if (! $this->option('persist')) {
            $this->newLine();
            $this->info('Re-run with --persist to create draft spool sources for these.');

            return CommandStatus::SUCCESS;
        }

        foreach ($mounts as $mount) {
            $this->persist($mount);
        }

        $this->newLine();
        $this->info('Drafts created on the local driver, so the existing mounts keep serving.');
        $this->line('Add each password under System → Cloud Faxing, test the connection, then switch the');
        $this->line('source to the SMB driver. Unmount only once it has been running that way happily.');

        return CommandStatus::SUCCESS;
    }

    private function persist(CifsMount $mount): void
    {
        if (! $mount->isMigratable()) {
            $this->warn("Skipping {$mount->mountPoint}: Kerberos mounts cannot be moved to stored credentials.");

            return;
        }

        $key = $this->keyFor($mount);

        if (FaxSpoolSource::findByKey($key) !== null) {
            $this->comment("Source [{$key}] already exists; leaving it alone.");

            return;
        }

        FaxSpoolSource::create([
            'key' => $key,
            'name' => "{$mount->host} ({$mount->share})",
            // Deliberately not enabled: an operator decides when a new source starts
            // being scanned.
            'enabled' => false,
            // Local until the password is supplied, so the mount that works today keeps
            // working and the change is reversible by flipping one column back.
            'driver' => FaxSpoolSource::DRIVER_LOCAL,
            'root_path' => $mount->mountPoint,
            // Prefer the address: connecting by name means a DNS lookup whose failure is
            // not covered by any of our timeouts.
            'smb_host' => $mount->address ?: $mount->host,
            'smb_username' => $mount->username,
            'smb_domain' => $mount->domain,
            'min_protocol' => $mount->version === null ? 'SMB2' : 'SMB'.Str::before($mount->version, '.'),
            'legacy_mount_path' => $mount->mountPoint,
        ]);

        $this->info("Created draft source [{$key}] for {$mount->mountPoint} (disabled, no password yet).");
    }

    private function keyFor(CifsMount $mount): string
    {
        return Str::limit(Str::slug("{$mount->host}-{$mount->share}"), 32, '');
    }
}
