<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\VoicemailDigest;
use App\Models\VoicemailDigestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoicemailDigestLogFactory extends Factory
{
    protected $model = VoicemailDigestLog::class;

    public function definition(): array
    {
        return [
            'voicemail_digest_id' => VoicemailDigest::factory(),
            'team_id' => Team::factory(),
            'start_date' => now()->subDay(),
            'end_date' => now(),
            'recipients' => [$this->faker->email, $this->faker->email],
            'subject' => 'Voicemail Digest',
            'recording_count' => $this->faker->numberBetween(0, 20),
            'status' => 'sent',
            'error_message' => null,
            'sent_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'recording_count' => 0,
            'sent_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'recording_count' => $this->faker->numberBetween(1, 20),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'recording_count' => 0,
            'error_message' => 'Connection timed out',
            'sent_at' => null,
        ]);
    }

    public function noRecordings(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_recordings',
            'recording_count' => 0,
            'sent_at' => null,
        ]);
    }
}
