<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\ExportBoardCheckForPeoplePraise;
use App\Jobs\PeoplePraiseApi\ExportBoardCheckForPeoplePraiseApi;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportPeoplePraiseBoardCheckFileTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_dispatches_file_export_job_when_file_method_is_selected()
    {
        Queue::fake();

        // Enable the board-check feature
        Helpers::enableSystemFeature('board-check');

        // Set export method to file
        Settings::factory()->create([
            'board_check_people_praise_export_method' => 'file',
        ]);

        $this->artisan('board-check:export-peoplepraise')
            ->expectsOutput('')
            ->assertExitCode(0);

        Queue::assertPushed(ExportBoardCheckForPeoplePraise::class);
        Queue::assertNotPushed(ExportBoardCheckForPeoplePraiseApi::class);
    }

    /** @test */
    public function it_dispatches_api_export_job_when_api_method_is_selected()
    {
        Queue::fake();

        // Enable the board-check feature
        Helpers::enableSystemFeature('board-check');

        // Set export method to api
        Settings::factory()->create([
            'board_check_people_praise_export_method' => 'api',
        ]);

        $this->artisan('board-check:export-peoplepraise')
            ->expectsOutput('')
            ->assertExitCode(0);

        Queue::assertPushed(ExportBoardCheckForPeoplePraiseApi::class);
        Queue::assertNotPushed(ExportBoardCheckForPeoplePraise::class);
    }

    /** @test */
    public function it_does_not_dispatch_jobs_when_feature_is_disabled()
    {
        Queue::fake();

        // Disable the board-check feature
        Helpers::disableSystemFeature('board-check');

        // Set export method to file
        Settings::factory()->create([
            'board_check_people_praise_export_method' => 'file',
        ]);

        $this->artisan('board-check:export-peoplepraise')
            ->expectsOutput('')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
