<?php

namespace App\Console;

use App\Console\Commands\Azure\SweepCredentials;
use App\Console\Commands\BetterEmails\ProcessFilesToEmail;
use App\Console\Commands\CheckInboundEmails;
use App\Console\Commands\ClearOldInboundEmails;
use App\Console\Commands\CsvExport\PurgeCsvExportLogs;
use App\Console\Commands\ExportPeoplePraiseBoardCheckFile;
use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Console\Commands\ISFaxing\CheckPendingFaxes;
use App\Console\Commands\ISFaxing\MonitorFaxBuildup;
use App\Console\Commands\ISFaxing\ProcessNewFaxes;
use App\Console\Commands\ISFaxing\ProcessRingCentralNewFaxes;
use App\Console\Commands\MessageExports\ProcessScheduledMessageExports;
use App\Console\Commands\MessageExports\PurgeMessageExportLogs;
use App\Console\Commands\ProcessingCleanup;
use App\Console\Commands\PurgeBoardCheckActivity;
use App\Console\Commands\SyncISData;
use App\Console\Commands\VoicemailDigests\ProcessScheduledVoicemailDigests;
use App\Console\Commands\VoicemailDigests\PurgeVoicemailDigestLogs;
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
        BuildRingCentralFaxDashboard::class,
        MonitorFaxBuildup::class,
        CheckInboundEmails::class,
        ExportPeoplePraiseBoardCheckFile::class,
        PurgeBoardCheckActivity::class,
        ProcessingCleanup::class,
        ProcessFilesToEmail::class,
        ClearOldInboundEmails::class,
        ProcessScheduledVoicemailDigests::class,
        PurgeVoicemailDigestLogs::class,
        PurgeCsvExportLogs::class,
        ProcessScheduledMessageExports::class,
        PurgeMessageExportLogs::class,
        SweepCredentials::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Every fax command reads the spool directories, which on a mounted share can
        // block. withoutOverlapping() is given an explicit expiry because the bare form
        // takes a 1440-minute lock: a scheduler killed while wedged would otherwise leave
        // the mutex held and silently stop the command running for a full day.
        // One dispatcher for every (source, provider) lane. It only reads the database,
        // so an unreachable spool can no longer block the scheduler — each lane is scanned
        // by its own queued job, with its own timeout.
        $schedule->command('isfax:scan')->everyMinute()->withoutOverlapping(5);
        $schedule->command('inbound-email:check')->everyMinute();
        $schedule->command('isfax:check-pending')->everyMinute()->withoutOverlapping(5);
        // Per-source spool snapshots, so the status pages never read a share inside a
        // web request. Dispatches queued jobs; it does not read the spool itself.
        $schedule->command('isfax:build-dashboards')->everyMinute()->withoutOverlapping(5);
        // Provider-level RingCentral data (the fax list and webhook heartbeat).
        $schedule->command('isfax:build-ringcentral-dashboard')->everyMinute()->withoutOverlapping(5);
        // One run covers every spool source; a run per provider would alert twice.
        $schedule->command('isfax:monitor')->everyThirtyMinutes()->withoutOverlapping(30);
        $schedule->command('telescope:prune --hours=1')->hourly();
        $schedule->command('intelligent-data:sync')->everyThirtyMinutes();
        $schedule->command('board-check:export-peoplepraise')->everyFifteenMinutes();
        $schedule->command('board-check:purge-activity')->daily();
        $schedule->command('app:processing-cleanup')->everyFifteenMinutes();
        $schedule->command('better-emails:process')->everyMinute()->withoutOverlapping();
        $schedule->command('app:clear-old-inbound-emails')->hourly();
        $schedule->command('voicemail-digest:process')->everyMinute()->withoutOverlapping();
        $schedule->command('voicemail-digest:purge-logs')->daily();
        $schedule->command('csv-export:purge-logs')->daily();
        $schedule->command('message-export:process')->everyMinute()->withoutOverlapping();
        $schedule->command('message-export:purge-logs')->daily();

        // The Azure token watcher. Daily is enough: the shortest threshold it alerts
        // on is three days, and Graph credential metadata does not change hourly.
        $schedule->command('azure:sweep-credentials')->dailyAt('06:15')->withoutOverlapping();
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
