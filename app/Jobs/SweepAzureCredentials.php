<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Azure\CredentialSweeper;
use App\Services\Azure\ExpiryAlerter;
use App\Services\Azure\GraphCredentials;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily credential sweep, and the alerts that follow it.
 *
 * Queued rather than run inline so the "Sweep now" button on the dashboard returns
 * immediately -- a tenant with several hundred app registrations is several Graph
 * pages, which is longer than a web request should hold.
 *
 * Unique for an hour: the scheduler and an impatient administrator pressing the
 * button must not sweep the same tenant twice at once, or both passes fight over
 * the same rows.
 */
class SweepAzureCredentials implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Failures here are configuration or Azure-side problems -- an expired secret,
     * withdrawn consent -- which retrying in ten minutes will not fix. One attempt,
     * recorded on the sweep row, and the dashboard's stale banner does the rest.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    public function __construct(public bool $alert = true) {}

    public function handle(CredentialSweeper $sweeper, ExpiryAlerter $alerter): void
    {
        if (! GraphCredentials::fromDataSource()->enabled) {
            Log::info('azure: credential sweep skipped, the Entra integration is disabled');

            return;
        }

        try {
            $sweep = $sweeper->sweep();
        } catch (Throwable $e) {
            // The sweep row already carries the reason; this is for the operator
            // watching logs rather than the dashboard.
            Log::error('azure: credential sweep failed', ['error' => $e->getMessage()]);

            return;
        }

        Log::info('azure: credential sweep complete', [
            'applications' => $sweep->applications,
            'service_principals' => $sweep->service_principals,
            'credentials' => $sweep->credentials_seen,
            'removed' => $sweep->credentials_removed,
        ]);

        if ($this->alert) {
            $alerter->run($sweep);
        }
    }
}
