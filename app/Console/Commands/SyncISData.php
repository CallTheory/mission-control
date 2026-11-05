<?php

namespace App\Console\Commands;

use App\Jobs\SyncMergeCommWebHooks;
use App\Models\DataSource;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class SyncISData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intelligent-data:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs MergeComm web trigger definitions from Intelligent Series';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Queues the sync of MergeComm web triggers out of Intelligent Series.
     *
     * The dispatch below was commented out at some point before the initial public commit,
     * which made this a no-op that the scheduler still ran every thirty minutes. Nothing
     * else writes `mergecomm_web_triggers`, so the catalogue the Inbound Email rules route
     * on was frozen wherever it last stood -- and empty on a fresh install.
     *
     * It is enabled again, but daily rather than half-hourly, because the MergeComm path is
     * the less-used half of inbound email: a rule with an `account` set goes out through
     * SendEmailToAmtelcoSMTP and never reads this table at all (see InboundRuleMatch). A
     * trigger catalogue that is at most a day stale is fine for the sites that do use it,
     * and System -> Tools has a manual run for when somebody has just added a trigger in
     * Intelligent Series and wants it now.
     */
    public function handle(): int
    {
        // DataSource::firstOrFail() inside the job throws without one, which on a schedule
        // would mean a failed job every day on any install that has not connected a switch.
        if (DataSource::count() === 0) {
            $this->warn('No data source is configured, so there is no Intelligent Series to sync from.');

            return CommandStatus::SUCCESS;
        }

        SyncMergeCommWebHooks::dispatch();

        $this->info('Queued the MergeComm web trigger sync.');

        return CommandStatus::SUCCESS;
    }
}
