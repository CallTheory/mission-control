<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CsvExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CsvExportLogFactory extends Factory
{
    protected $model = CsvExportLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'filters' => [
                'start_date' => now()->subHour()->format('Y-m-d\TH:i'),
                'end_date' => now()->format('Y-m-d\TH:i'),
                'client_number' => null,
                'ani' => null,
                'call_type' => null,
                'agent' => null,
                'min_duration' => null,
                'max_duration' => null,
                'keyword' => null,
                'keyword_search' => null,
                'sort_by' => 'statCallStart.Stamp',
                'sort_direction' => 'desc',
                'has_any' => true,
                'has_messages' => false,
                'has_recordings' => false,
                'has_video' => false,
            ],
            'result_count' => $this->faker->numberBetween(1, 500),
            'filename' => 'call-log-export-'.now()->format('Y-m-d_His').'.csv',
            'status' => 'completed',
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'result_count' => $this->faker->numberBetween(1, 500),
            'filename' => 'call-log-export-'.now()->format('Y-m-d_His').'.csv',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'result_count' => 0,
            'filename' => null,
            'error_message' => 'Connection timed out',
        ]);
    }
}
