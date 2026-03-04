<?php

declare(strict_types=1);

namespace App\Console\Commands\VoicemailDigests;

use App\Models\VoicemailDigestLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class PurgeVoicemailDigestLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voicemail-digest:purge-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old voicemail digest log records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = config('utilities.voicemail-digest.days_to_keep', 30);

        $deleted = VoicemailDigestLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Purged {$deleted} voicemail digest log(s) older than {$days} days.");

        return CommandStatus::SUCCESS;
    }
}
