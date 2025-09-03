<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EnterpriseHost;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EnterpriseHostFactory extends Factory
{
    protected $model = EnterpriseHost::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'senderID' => $this->faker->unique()->userName(),
            'securityCode' => Str::random(16),
            'enabled' => true,
            'callback_url' => $this->faker->optional()->url(),
            'phone_numbers' => ['+1' . $this->faker->numerify('##########')],
            'team_id' => null, // Will be set by tests if needed
            'message_count' => 0,
            'last_message_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function withTeam(): static
    {
        return $this->state(fn () => ['team_id' => Team::factory()]);
    }

    public function withMessages(int $count = 5): static
    {
        return $this->state(fn () => [
            'message_count' => $count,
            'last_message_at' => $this->faker->dateTimeBetween('-1 week'),
        ]);
    }
}