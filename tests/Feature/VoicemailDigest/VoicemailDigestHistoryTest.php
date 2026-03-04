<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Livewire\Utilities\VoicemailDigestHistory;
use App\Models\Team;
use App\Models\User;
use App\Models\VoicemailDigest;
use App\Models\VoicemailDigestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class VoicemailDigestHistoryTest extends TestCase
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
            'utility_voicemail_digest' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeatureFlag();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('feature-flags');
        parent::tearDown();
    }

    public function test_history_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest.history'));

        $response->assertStatus(200)
            ->assertViewIs('utilities.voicemail-digest-history');
    }

    public function test_history_page_returns_404_when_feature_disabled(): void
    {
        Storage::deleteDirectory('feature-flags');

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest.history'));

        $response->assertStatus(404);
    }

    public function test_history_page_returns_403_for_personal_team(): void
    {
        $personalTeam = Team::factory()->create([
            'personal_team' => true,
            'utility_voicemail_digest' => true,
        ]);

        $this->user->teams()->attach($personalTeam, ['role' => 'admin']);
        $this->user->switchTeam($personalTeam);
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest.history'));

        $response->assertStatus(403);
    }

    public function test_history_component_displays_logs(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'subject' => 'Test Digest',
            'recording_count' => 5,
        ]);

        Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class)
            ->assertSee($digest->name)
            ->assertSee('5')
            ->assertSee('Sent');
    }

    public function test_history_component_filters_by_status(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        VoicemailDigestLog::factory()->failed()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        // Without filter, both status badges are visible
        $component = Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class);

        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(1, substr_count($html, 'bg-red-100'));

        // With filter, only sent logs are visible
        $component->set('filterStatus', 'sent');
        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(0, substr_count($html, 'bg-red-100'));
    }

    public function test_history_component_filters_by_schedule(): void
    {
        $digest1 = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Schedule Alpha',
        ]);
        $digest2 = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Schedule Beta',
        ]);

        VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest1->id,
            'team_id' => $this->team->id,
        ]);

        VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest2->id,
            'team_id' => $this->team->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class)
            ->set('filterSchedule', $digest1->id)
            ->assertSee('Schedule Alpha');

        // The table body should only contain one data row for Schedule Alpha
        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
    }

    public function test_history_component_only_shows_team_logs(): void
    {
        $otherTeam = Team::factory()->create();
        $digest = VoicemailDigest::factory()->create(['team_id' => $otherTeam->id]);

        VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $otherTeam->id,
            'subject' => 'Other Team Digest',
        ]);

        Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class)
            ->assertDontSee('Other Team Digest')
            ->assertSee('No digest history found.');
    }

    public function test_resend_dispatches_job(): void
    {
        Queue::fake();

        $digest = VoicemailDigest::factory()->daily()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class)
            ->call('resend', $log->id)
            ->assertHasNoErrors();

        Queue::assertPushed(\App\Jobs\SendVoicemailDigest::class);
    }

    public function test_resend_flashes_confirmation_message(): void
    {
        Queue::fake();

        $digest = VoicemailDigest::factory()->daily()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->sent()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(VoicemailDigestHistory::class)
            ->call('resend', $log->id)
            ->assertSee('Voicemail digest has been queued for resend.');
    }

    public function test_model_mark_as_sent(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->queued()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $log->markAsSent(10);
        $log->refresh();

        $this->assertEquals('sent', $log->status);
        $this->assertEquals(10, $log->recording_count);
        $this->assertNotNull($log->sent_at);
    }

    public function test_model_mark_as_failed(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->queued()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $log->markAsFailed('Connection refused');
        $log->refresh();

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Connection refused', $log->error_message);
    }

    public function test_model_mark_as_no_recordings(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->queued()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $log->markAsNoRecordings();
        $log->refresh();

        $this->assertEquals('no_recordings', $log->status);
    }

    public function test_model_for_team_scope(): void
    {
        $otherTeam = Team::factory()->create();
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $otherDigest = VoicemailDigest::factory()->create(['team_id' => $otherTeam->id]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $otherDigest->id,
            'team_id' => $otherTeam->id,
        ]);

        $logs = VoicemailDigestLog::forTeam($this->team->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->team->id, $logs->first()->team_id);
    }

    public function test_purge_command_deletes_old_logs(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(31),
        ]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('voicemail-digest:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, VoicemailDigestLog::all());
    }

    public function test_purge_command_respects_config(): void
    {
        config(['utilities.voicemail-digest.days_to_keep' => 10]);

        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(15),
        ]);

        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('voicemail-digest:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, VoicemailDigestLog::all());
    }

    public function test_log_belongs_to_voicemail_digest(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($digest->id, $log->voicemailDigest->id);
    }

    public function test_log_belongs_to_team(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($this->team->id, $log->team->id);
    }

    public function test_log_casts_recipients_to_array(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        $log = VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
            'recipients' => ['test@example.com', 'other@example.com'],
        ]);

        $log->refresh();
        $this->assertIsArray($log->recipients);
        $this->assertCount(2, $log->recipients);
    }

    public function test_log_cascades_on_digest_delete(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);
        VoicemailDigestLog::factory()->create([
            'voicemail_digest_id' => $digest->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertCount(1, VoicemailDigestLog::all());

        $digest->forceDelete();

        $this->assertCount(0, VoicemailDigestLog::all());
    }

    private function enableSystemFeatureFlag(): void
    {
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('voicemail-digest');
        Storage::put('feature-flags/voicemail-digest.flag', $encrypted);
    }
}
