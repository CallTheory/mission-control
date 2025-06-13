<?php

namespace App\Console\Commands;

use App\Jobs\ExportBoardCheckForPeoplePraise;
use App\Jobs\PeoplePraiseApi\ExportBoardCheckForPeoplePraiseApi;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ExportPeoplePraiseBoardCheckFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'board-check:export-peoplepraise';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the people praise file.';

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
        if (Helpers::isSystemFeatureEnabled('board-check')) {
            $settings = Settings::first();

            if ($settings->board_check_people_praise_export_method === 'file') {
                ExportBoardCheckForPeoplePraise::dispatch();
            } elseif ($settings->board_check_people_praise_export_method === 'api') {
                ExportBoardCheckForPeoplePraiseApi::dispatch();
            }
        }

        return CommandStatus::SUCCESS;
    }
}
