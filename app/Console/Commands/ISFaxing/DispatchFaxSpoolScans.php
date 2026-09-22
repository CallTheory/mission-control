<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Jobs\ScanFaxSpoolLane;
use App\Models\FaxSpoolSource;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxLaneRegistry;
use App\Services\Faxing\FaxSourceHealth;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Hands each (source, provider) spool to its own queued job.
 *
 * This command must never touch the filesystem. Its whole purpose is that an unreachable
 * spool can only ever block a disposable queue worker, and a dispatcher that stat()ed a
 * dead mount would hand that hang straight back to the scheduler — which is the bug this
 * replaces.
 */
class DispatchFaxSpoolScans extends Command
{
    protected $signature = 'isfax:scan
        {--source=* : Limit to these spool source keys}
        {--provider=* : Limit to sources that can send through these providers}
        {--sync : Run the lanes inline instead of queueing them}
        {--force : Scan even a source that is being rested after repeated failures}';

    protected $description = 'Scan every enabled fax spool source for new faxes to send';

    public function handle(FaxLaneRegistry $registry, FaxSourceHealth $health): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        $sources = $registry->enabled(
            (array) $this->option('source'),
            (array) $this->option('provider'),
        );

        if ($sources === []) {
            $this->comment('No enabled fax spool sources.');

            return CommandStatus::SUCCESS;
        }

        foreach ($sources as $source) {
            $this->dispatchLane($source, $health);
        }

        return CommandStatus::SUCCESS;
    }

    private function dispatchLane(FaxSpoolSource $source, FaxSourceHealth $health): void
    {
        // --sync deliberately ignores the cooldown as well as the queue: someone
        // debugging an unreachable server wants the failure in their own terminal, not a
        // line saying it was skipped.
        if (! $this->option('sync') && ! $this->option('force') && $health->inCooldown($source->key)) {
            $this->comment("Resting {$source->key} for another {$health->cooldownRemaining($source->key)}s.");

            return;
        }

        if ($this->option('sync')) {
            ScanFaxSpoolLane::dispatchSync($source->key);
            $this->info("Scanned {$source->key}.");

            return;
        }

        ScanFaxSpoolLane::dispatch($source->key);
        $this->info("Dispatched {$source->key}.");
    }
}
