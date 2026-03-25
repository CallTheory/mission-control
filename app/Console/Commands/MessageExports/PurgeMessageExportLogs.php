<?php

declare(strict_types=1);

namespace App\Console\Commands\MessageExports;

use App\Models\MessageExportLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandStatus;

class PurgeMessageExportLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-export:purge-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old message export log records and temp files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = config('utilities.message-export.days_to_keep', 90);

        // Delete old log records
        $logs = MessageExportLog::where('created_at', '<', Carbon::now()->subDays($days))->get();

        $deletedFiles = 0;
        foreach ($logs as $log) {
            if ($log->file_path && Storage::exists($log->file_path)) {
                Storage::delete($log->file_path);
                $deletedFiles++;
            }
        }

        $deleted = MessageExportLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Purged {$deleted} message export log(s) older than {$days} days. Removed {$deletedFiles} temp file(s).");

        // Also clean up orphaned temp files older than 24 hours
        $files = Storage::files('message-exports');
        $orphaned = 0;
        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if ($lastModified < Carbon::now()->subDay()->getTimestamp()) {
                $logId = pathinfo($file, PATHINFO_FILENAME);
                if (! MessageExportLog::where('id', $logId)->where('file_path', $file)->exists()) {
                    Storage::delete($file);
                    $orphaned++;
                }
            }
        }

        if ($orphaned > 0) {
            $this->info("Cleaned up {$orphaned} orphaned temp file(s).");
        }

        return CommandStatus::SUCCESS;
    }
}
