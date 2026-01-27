<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\VoicemailDigest;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoicemailDigestFactory extends Factory
{
    protected $model = VoicemailDigest::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(3, true),
            'client_number' => $this->faker->optional()->numerify('####'),
            'billing_code' => $this->faker->optional()->numerify('###'),
            'recipients' => [
                $this->faker->email,
                $this->faker->email,
            ],
            'subject' => 'Voicemail Digest',
            'schedule_type' => $this->faker->randomElement(['hourly', 'daily', 'weekly', 'monthly']),
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 0,
            'schedule_day_of_month' => 1,
            'include_transcription' => true,
            'include_call_metadata' => true,
            'enabled' => true,
            'last_run_at' => null,
            'next_run_at' => null,
            'timezone' => 'America/New_York',
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'hourly',
            'schedule_time' => null,
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'daily',
            'schedule_time' => '08:00',
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'weekly',
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 1, // Monday
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'monthly',
            'schedule_time' => '08:00',
            'schedule_day_of_month' => 1,
        ]);
    }
}
