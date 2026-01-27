<?php

declare(strict_types=1);

namespace App\Console\Commands\VoicemailDigests;

use App\Jobs\SendVoicemailDigest;
use App\Models\Stats\Helpers;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProcessScheduledVoicemailDigests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voicemail-digest:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled voicemail digest dispatches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('voicemail-digest')) {
            $this->info('Voicemail digest feature is not enabled.');

            return CommandStatus::SUCCESS;
        }

        $schedules = VoicemailDigest::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', Carbon::now());
            })
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No voicemail digest schedules are due.');

            return CommandStatus::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            // Check if team has the utility enabled
            if (! $schedule->team->utility_voicemail_digest) {
                continue;
            }

            $this->info("Processing voicemail digest schedule: {$schedule->name} (ID: {$schedule->id})");

            // Get the date range for this schedule
            [$startDate, $endDate] = $schedule->getDateRange();

            // Dispatch the job
            SendVoicemailDigest::dispatch($schedule, $startDate, $endDate);

            // Update schedule timestamps
            $schedule->last_run_at = Carbon::now();
            $schedule->next_run_at = $schedule->calculateNextRunAt();
            $schedule->save();

            $this->info("Dispatched job for schedule {$schedule->id}. Next run: {$schedule->next_run_at}");
        }

        return CommandStatus::SUCCESS;
    }
}
