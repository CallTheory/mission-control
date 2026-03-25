<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Models\MessageExport;
use App\Models\MessageExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MessageExportLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_message_export' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('message-exports');
        parent::tearDown();
    }

    public function test_mark_as_completed(): void
    {
        $log = MessageExportLog::factory()->queued()->create([
            'team_id' => $this->team->id,
        ]);

        $log->markAsCompleted(42, 'message-exports/test.csv');
        $log->refresh();

        $this->assertEquals('completed', $log->status);
        $this->assertEquals(42, $log->message_count);
        $this->assertEquals('message-exports/test.csv', $log->file_path);
    }

    public function test_mark_as_sent(): void
    {
        $log = MessageExportLog::factory()->queued()->create([
            'team_id' => $this->team->id,
        ]);

        $log->markAsSent(15);
        $log->refresh();

        $this->assertEquals('sent', $log->status);
        $this->assertEquals(15, $log->message_count);
        $this->assertNotNull($log->sent_at);
    }

    public function test_mark_as_failed(): void
    {
        $log = MessageExportLog::factory()->queued()->create([
            'team_id' => $this->team->id,
        ]);

        $log->markAsFailed('Connection refused');
        $log->refresh();

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Connection refused', $log->error_message);
    }

    public function test_mark_as_no_messages(): void
    {
        $log = MessageExportLog::factory()->queued()->create([
            'team_id' => $this->team->id,
        ]);

        $log->markAsNoMessages();
        $log->refresh();

        $this->assertEquals('no_messages', $log->status);
    }

    public function test_for_team_scope(): void
    {
        $otherTeam = Team::factory()->create();

        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
        ]);

        MessageExportLog::factory()->create([
            'team_id' => $otherTeam->id,
        ]);

        $logs = MessageExportLog::forTeam($this->team->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->team->id, $logs->first()->team_id);
    }

    public function test_log_belongs_to_message_export(): void
    {
        $export = MessageExport::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $log = MessageExportLog::factory()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($export->id, $log->messageExport->id);
    }

    public function test_log_belongs_to_team(): void
    {
        $log = MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($this->team->id, $log->team->id);
    }

    public function test_log_belongs_to_user(): void
    {
        $log = MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $log->user->id);
    }

    public function test_log_user_is_nullable(): void
    {
        $log = MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => null,
        ]);

        $this->assertNull($log->user);
    }

    public function test_log_casts_dates(): void
    {
        $log = MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $log->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $log->end_date);
    }

    public function test_purge_command_deletes_old_logs(): void
    {
        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(91),
        ]);

        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('message-export:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, MessageExportLog::all());
    }

    public function test_purge_command_respects_config(): void
    {
        config(['utilities.message-export.days_to_keep' => 10]);

        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(15),
        ]);

        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('message-export:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, MessageExportLog::all());
    }

    public function test_purge_command_deletes_associated_files(): void
    {
        $filePath = 'message-exports/old-export.csv';
        Storage::put($filePath, encrypt('old csv content'));

        MessageExportLog::factory()->create([
            'team_id' => $this->team->id,
            'file_path' => $filePath,
            'created_at' => now()->subDays(91),
        ]);

        $this->artisan('message-export:purge-logs')
            ->assertExitCode(0);

        $this->assertFalse(Storage::exists($filePath));
    }
}
