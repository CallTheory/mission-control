<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MessageExport;
use App\Models\MessageExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageExportLogFactory extends Factory
{
    protected $model = MessageExportLog::class;

    public function definition(): array
    {
        return [
            'message_export_id' => MessageExport::factory(),
            'team_id' => Team::factory(),
            'user_id' => null,
            'export_name' => $this->faker->words(3, true),
            'client_number' => $this->faker->numerify('####'),
            'start_date' => now()->subDay(),
            'end_date' => now(),
            'message_count' => $this->faker->numberBetween(0, 100),
            'status' => 'completed',
            'error_message' => null,
            'file_path' => null,
            'sent_at' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'message_count' => 0,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'message_count' => $this->faker->numberBetween(1, 100),
            'file_path' => 'message-exports/' . $this->faker->uuid() . '.csv',
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'message_count' => $this->faker->numberBetween(1, 100),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'message_count' => 0,
            'error_message' => 'Connection timed out',
        ]);
    }

    public function noMessages(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_messages',
            'message_count' => 0,
        ]);
    }
}
