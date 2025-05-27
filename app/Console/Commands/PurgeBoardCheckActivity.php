<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use Symfony\Component\Console\Command\Command as CommandStatus;

class PurgeBoardCheckActivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'board-check:purge-activity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purges activity records from the board check database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        BoardCheckActivity::where('created_at', '<', Carbon::now()->subDays(14))->delete();

        return CommandStatus::SUCCESS;
    }
}
