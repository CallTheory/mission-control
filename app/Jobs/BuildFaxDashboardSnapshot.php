<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxDashboardSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reads one spool source's folders and caches the result for the status pages.
 *
 * Queued for the same reason the scans are: reading a source is the operation that can
 * block on an unreachable share, and a job carries a timeout that a scheduled command
 * running inline does not.
 */
class BuildFaxDashboardSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public string $sourceKey = 'mfax';

    public function __construct(string $sourceKey)
    {
        $this->sourceKey = $sourceKey;
        $this->onQueue('fax-scan');
    }

    public function uniqueId(): string
    {
        return "dashboard:{$this->sourceKey}";
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(FaxDashboardSnapshot $snapshots): void
    {
        $source = FaxSpoolSource::findByKey($this->sourceKey);

        if ($source === null) {
            return;
        }

        $snapshots->build($source);
    }
}
