<?php

namespace App\Console\Commands;

// use App\Jobs\SyncMergeCommWebHooks;
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
    protected $description = 'Syncs configuration data for mergecomm';

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
     * Execute the console command.
     */
    public function handle(): int
    {
        // SyncMergeCommWebHooks::dispatch();

        return CommandStatus::SUCCESS;
    }
}
