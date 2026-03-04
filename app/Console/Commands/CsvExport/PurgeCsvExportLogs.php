<?php

declare(strict_types=1);

namespace App\Console\Commands\CsvExport;

use App\Models\CsvExportLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class PurgeCsvExportLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv-export:purge-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old CSV export log records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = config('utilities.csv-export.days_to_keep', 90);

        $deleted = CsvExportLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Purged {$deleted} CSV export log(s) older than {$days} days.");

        return CommandStatus::SUCCESS;
    }
}
