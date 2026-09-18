<?php

declare(strict_types=1);

namespace App\Console\Commands\Azure;

use App\Models\AzureCredential;
use App\Services\Azure\CredentialSweeper;
use App\Services\Azure\ExpiryAlerter;
use App\Services\Azure\GraphCredentials;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Throwable;

/**
 * The scheduled entry point for the Azure token watcher.
 *
 * Runs the sweep in the foreground rather than dispatching the job, so the
 * scheduler's own failure handling and output apply and an operator running this by
 * hand sees what happened.
 */
class SweepCredentials extends Command
{
    protected $signature = 'azure:sweep-credentials
                            {--no-alerts : Sweep and store, but send no expiry alerts}
                            {--force : Sweep even when the Entra integration is switched off}';

    protected $description = 'Sweep Entra app registration credentials and alert on upcoming expiries';

    public function handle(CredentialSweeper $sweeper, ExpiryAlerter $alerter): int
    {
        $credentials = GraphCredentials::fromDataSource();

        if (! $credentials->configured()) {
            $this->error('Entra ID is not configured. Add the credentials on System -> Integrations.');

            return CommandStatus::FAILURE;
        }

        if (! $credentials->enabled && ! $this->option('force')) {
            $this->warn('The Entra integration is switched off. Use --force to sweep anyway.');

            return CommandStatus::SUCCESS;
        }

        try {
            $sweep = $sweeper->sweep();
        } catch (Throwable $e) {
            $this->error('Sweep failed: '.$e->getMessage());

            return CommandStatus::FAILURE;
        }

        $this->info(sprintf(
            'Swept %d app registrations and %d service principals: %d credentials (%d new, %d no longer present).',
            $sweep->applications,
            $sweep->service_principals,
            $sweep->credentials_seen,
            $sweep->credentials_added,
            $sweep->credentials_removed,
        ));

        if ($this->option('no-alerts')) {
            $this->line('Alerting skipped.');
        } else {
            $sent = $alerter->run($sweep);
            $this->line($sent === 0
                ? 'No credentials crossed an alert threshold.'
                : 'Alerted on '.$sent.' credential'.($sent === 1 ? '' : 's').'.');
        }

        $this->soonestExpiries();

        return CommandStatus::SUCCESS;
    }

    /**
     * The ten nearest expiries, which is what anyone running this by hand is
     * actually asking for.
     */
    private function soonestExpiries(): void
    {
        $credentials = AzureCredential::query()
            ->present()
            ->orderBy('end_utc')
            ->limit(10)
            ->get();

        if ($credentials->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->table(
            ['App', 'Type', 'Credential', 'Expires (UTC)', 'Days', 'Status'],
            $credentials->map(fn (AzureCredential $credential): array => [
                $credential->app_name,
                $credential->cred_type->label(),
                $credential->label(),
                $credential->end_utc->toDateTimeString(),
                $credential->daysRemaining(),
                $credential->status()->label().($credential->acknowledged ? ' (ack)' : ''),
            ])->all(),
        );
    }
}
