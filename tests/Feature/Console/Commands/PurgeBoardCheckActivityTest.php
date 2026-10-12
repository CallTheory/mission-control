<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeBoardCheckActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_old_board_check_activities()
    {
        // Create old and new activities
        $oldActivity = BoardCheckActivity::factory()->create([
            'created_at' => Carbon::now()->subDays(15),
        ]);

        $newActivity = BoardCheckActivity::factory()->create([
            'created_at' => Carbon::now()->subDays(13),
        ]);

        $this->artisan('board-check:purge-activity')
            ->assertExitCode(0);

        // Assert old activity was deleted
        $this->assertDatabaseMissing('activities', [
            'id' => $oldActivity->id,
        ]);

        // Assert new activity was preserved
        $this->assertDatabaseHas('activities', [
            'id' => $newActivity->id,
        ]);
    }

    public function test_it_handles_empty_activities_table()
    {
        $this->artisan('board-check:purge-activity')
            ->assertExitCode(0);

        // Command should complete successfully with no errors
        $this->assertTrue(true);
    }

    public function test_it_preserves_activities_exactly_14_days_old()
    {
        // Create an activity exactly 14 days old
        $activity = BoardCheckActivity::factory()->create([
            'created_at' => Carbon::now()->subDays(14),
        ]);

        $this->artisan('board-check:purge-activity')
            ->assertExitCode(0);

        // Assert activity was preserved
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
        ]);
    }
}
