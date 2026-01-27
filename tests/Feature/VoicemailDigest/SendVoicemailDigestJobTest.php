<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Jobs\SendVoicemailDigest;
use App\Models\Team;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendVoicemailDigestJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_job_is_configured_correctly(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $startDate = Carbon::now()->subDay();
        $endDate = Carbon::now();

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        $this->assertEquals('voicemail-digest', $job->queue);
        $this->assertEquals(3, $job->tries);
        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_job_stores_schedule_and_dates(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $startDate = Carbon::parse('2026-01-25 08:00:00');
        $endDate = Carbon::parse('2026-01-26 17:00:00');

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        $this->assertEquals($digest->id, $job->schedule->id);
        $this->assertEquals('2026-01-25 08:00:00', $job->startDate->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 17:00:00', $job->endDate->format('Y-m-d H:i:s'));
    }

    public function test_job_deletes_when_schedule_model_is_missing(): void
    {
        $team = Team::factory()->create();
        $digest = VoicemailDigest::factory()->create(['team_id' => $team->id]);
        $startDate = Carbon::now()->subDay();
        $endDate = Carbon::now();

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        // Delete the model
        $digest->forceDelete();

        // Job should be configured to delete when models are missing
        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_job_can_be_serialized_and_unserialized(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $startDate = Carbon::parse('2026-01-25 08:00:00');
        $endDate = Carbon::parse('2026-01-26 17:00:00');

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SendVoicemailDigest::class, $unserialized);
        $this->assertEquals($digest->id, $unserialized->schedule->id);
        $this->assertEquals('2026-01-25 08:00:00', $unserialized->startDate->format('Y-m-d H:i:s'));
    }

    public function test_job_respects_schedule_timezone(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'timezone' => 'America/Los_Angeles',
        ]);

        $startDate = Carbon::parse('2026-01-25 08:00:00', 'America/Los_Angeles');
        $endDate = Carbon::parse('2026-01-26 17:00:00', 'America/Los_Angeles');

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        $this->assertEquals('America/Los_Angeles', $job->startDate->timezone->getName());
        $this->assertEquals('America/Los_Angeles', $job->endDate->timezone->getName());
    }

    public function test_job_uses_correct_queue_name(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $job = new SendVoicemailDigest($digest, Carbon::now()->subDay(), Carbon::now());

        $this->assertEquals('voicemail-digest', $job->queue);
    }

    public function test_job_has_retry_configuration(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $job = new SendVoicemailDigest($digest, Carbon::now()->subDay(), Carbon::now());

        $this->assertEquals(3, $job->tries);
    }

    public function test_job_properties_are_accessible(): void
    {
        $team = Team::factory()->create(['name' => 'Test Team']);
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'name' => 'Test Digest',
            'recipients' => ['test@example.com'],
            'subject' => 'Test Subject',
        ]);

        $startDate = Carbon::parse('2026-01-25 08:00:00');
        $endDate = Carbon::parse('2026-01-26 17:00:00');

        $job = new SendVoicemailDigest($digest, $startDate, $endDate);

        $this->assertEquals('Test Digest', $job->schedule->name);
        $this->assertEquals(['test@example.com'], $job->schedule->recipients);
        $this->assertEquals('Test Subject', $job->schedule->subject);
        $this->assertEquals($team->id, $job->schedule->team_id);
    }
}
