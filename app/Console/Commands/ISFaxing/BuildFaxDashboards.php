<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Jobs\BuildFaxDashboardSnapshot;
use App\Models\FaxSpoolSource;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxSourceHealth;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Refreshes every spool source's cached status snapshot.
 *
 * Like isfax:scan this only dispatches; reading the folders happens in a queued job with
 * its own timeout, so an unreachable source cannot block the scheduler or the other
 * sources' snapshots.
 */
class BuildFaxDashboards extends Command
{
    protected $signature = 'isfax:build-dashboards
        {--source=* : Limit to these spool source keys}
        {--sync : Build inline instead of queueing}';

    protected $description = 'Build the cached fax status snapshot for each spool source';

    public function handle(FaxSourceHealth $health): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        $sources = FaxSpoolSource::query()
            ->enabled()
            ->when($this->option('source'), fn ($query) => $query->whereIn('key', (array) $this->option('source')))
            ->orderBy('key')
            ->get();

        foreach ($sources as $source) {
            // A source that is being rested keeps its previous snapshot rather than
            // spending a worker to re-confirm it is still unreachable.
            if (! $this->option('sync') && $health->inCooldown($source->key)) {
                continue;
            }

            $this->option('sync')
                ? BuildFaxDashboardSnapshot::dispatchSync($source->key)
                : BuildFaxDashboardSnapshot::dispatch($source->key);
        }

        return CommandStatus::SUCCESS;
    }
}
