<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Mail\FaxBuildupAlert;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxSpool;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Throwable;

/**
 * Notices when files stop moving through a spool source.
 *
 * Reachability is not the thing being watched here. Only one Intelligent Series server
 * processes faxes at a time, so every other source is legitimately sitting empty — an
 * empty folder is the healthy resting state, not a fault. What matters is a file that
 * arrived and is *still there*, which means something stopped processing it.
 */
class MonitorFaxBuildup extends Command
{
    /**
     * Files older than this in a spool folder mean something has stopped processing them.
     */
    private const STALE_MINUTES = 15;

    protected $signature = 'isfax:monitor
        {fax_provider? : Deprecated and ignored; every source is checked}
        {--source=* : Limit to these spool source keys}';

    protected $description = 'Checks the fax spool folders to determine if faxes are not being processed.';

    public function handle(): int
    {
        // Cloud faxing being switched off is a configuration state, not a fault: every
        // other isfax: command exits cleanly here, and failing instead makes the
        // scheduler log an error twice an hour for a system nobody asked to run.
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        // Every enabled source, deliberately without asking whether its provider is
        // configured: files piling up with nothing able to send them is precisely when
        // this alert matters most.
        $sources = FaxSpoolSource::query()
            ->enabled()
            ->when($this->option('source'), fn ($query) => $query->whereIn('key', (array) $this->option('source')))
            ->orderBy('key')
            ->get();

        $filling = [];

        foreach ($sources as $source) {
            if ($this->checkSource($source)) {
                $filling[] = $source->key;
            }
        }

        $this->warnIfSeveralSourcesAreFilling($filling);

        return CommandStatus::SUCCESS;
    }

    /**
     * @return bool whether this source currently holds any spool files at all
     */
    private function checkSource(FaxSpoolSource $source): bool
    {
        $spool = new FaxSpool;
        $provider = $source->pinned_provider->value ?? 'mfax';

        $paths = [];
        $stuckFiles = [];
        $holdsFiles = false;

        foreach (['tosend', 'fail', 'sent'] as $folder) {
            try {
                $files = $spool->files($provider, $folder, $source->key);
            } catch (Throwable $e) {
                // An unreachable source is not a buildup. It is recorded by the scan
                // lane's health tracking; alerting here too would page someone about a
                // standby server that is simply switched off.
                Log::warning("isfax:monitor could not read [{$source->key}/{$folder}]: ".$e->getMessage());

                continue;
            }

            $spoolFiles = array_values(array_filter($files, fn (array $f) => in_array($f['type'], ['cap', 'fs'], true)));

            if ($folder === 'tosend' && $spoolFiles !== []) {
                $holdsFiles = true;
            }

            $stale = array_values(array_filter(
                $spoolFiles,
                fn (array $f) => Carbon::parse($f['modified_at'])->lessThan(Carbon::now()->subMinutes(self::STALE_MINUTES))
            ));

            if ($stale === []) {
                continue;
            }

            $paths[] = $spool->path($provider, $folder, $source->key);

            foreach ($stale as $file) {
                $this->info("[{$source->key}] [{$folder}] [{$file['name']}] {$file['modified_at']} account=".($file['account'] ?? 'unknown'));

                $stuckFiles[] = $file + ['folder' => FaxSpool::folderLabel($folder)];
            }
        }

        $stalePendingCount = PendingFax::pending()
            ->where('spool_source_key', $source->key)
            ->where('created_at', '<', Carbon::now()->subMinutes(30))
            ->count();

        if ($stalePendingCount > 0) {
            $this->info("[{$source->key}] {$stalePendingCount} pending faxes older than 30 minutes");
            $paths[] = "pending_faxes table: {$stalePendingCount} records older than 30 minutes";
        }

        if ($paths !== []) {
            $this->info("[{$source->key}] found unexpected fax files older than ".self::STALE_MINUTES.' minutes');
            Mail::queue(new FaxBuildupAlert($paths, $stuckFiles, $provider, $source->key, $source->name));
        }

        return $holdsFiles;
    }

    /**
     * Only one Intelligent Series fax service should be processing at a time, so files
     * accumulating in two sources at once means two are running — a misconfiguration
     * nobody would otherwise notice, because each source looks fine on its own.
     *
     * A warning rather than an alert: a failover legitimately leaves files on both for a
     * short while, and this runs often enough to catch the transient case.
     *
     * @param  array<int, string>  $filling
     */
    private function warnIfSeveralSourcesAreFilling(array $filling): void
    {
        if (count($filling) < 2) {
            return;
        }

        $this->warn('Several fax spool sources hold files at once: '.implode(', ', $filling));
        Log::warning('Several fax spool sources hold unsent files at once, which usually means more than one Intelligent Series fax service is active.', [
            'sources' => $filling,
        ]);
    }
}
