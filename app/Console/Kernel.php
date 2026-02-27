<?php

namespace App\Console;

use App\Console\Commands\BetterEmails\ProcessFilesToEmail;
use App\Console\Commands\CheckInboundEmails;
use App\Console\Commands\ClearOldInboundEmails;
use App\Console\Commands\ExportPeoplePraiseBoardCheckFile;
use App\Console\Commands\ISFaxing\CheckPendingFaxes;
use App\Console\Commands\ISFaxing\MonitorFaxBuildup;
use App\Console\Commands\ISFaxing\ProcessNewFaxes;
use App\Console\Commands\ISFaxing\ProcessRingCentralNewFaxes;
use App\Console\Commands\ProcessingCleanup;
use App\Console\Commands\PurgeBoardCheckActivity;
use App\Console\Commands\SyncISData;
use App\Console\Commands\VoicemailDigests\ProcessScheduledVoicemailDigests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SyncISData::class,
        ProcessNewFaxes::class,
        ProcessRingCentralNewFaxes::class,
        CheckPendingFaxes::class,
        MonitorFaxBuildup::class,
        CheckInboundEmails::class,
        ExportPeoplePraiseBoardCheckFile::class,
        PurgeBoardCheckActivity::class,
        ProcessingCleanup::class,
        ProcessFilesToEmail::class,
        ClearOldInboundEmails::class,
        ProcessScheduledVoicemailDigests::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('isfax:process')->everyMinute();
        $schedule->command('isfax:process-ring-central')->everyMinute();
        $schedule->command('inbound-email:check')->everyMinute();
        $schedule->command('isfax:check-pending')->everyMinute()->withoutOverlapping();
        $schedule->command('isfax:monitor mfax')->everyThirtyMinutes();
        $schedule->command('isfax:monitor ringcentral')->everyThirtyMinutes();
        $schedule->command('telescope:prune --hours=1')->hourly();
        $schedule->command('intelligent-data:sync')->everyThirtyMinutes();
        $schedule->command('board-check:export-peoplepraise')->everyFifteenMinutes();
        $schedule->command('board-check:purge-activity')->daily();
        $schedule->command('app:processing-cleanup')->everyFifteenMinutes();
        $schedule->command('better-emails:process')->everyMinute()->withoutOverlapping();
        $schedule->command('app:clear-old-inbound-emails')->hourly();
        $schedule->command('voicemail-digest:process')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
