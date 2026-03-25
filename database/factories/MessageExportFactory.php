<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MessageExport;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageExportFactory extends Factory
{
    protected $model = MessageExport::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(3, true),
            'client_number' => $this->faker->numerify('####'),
            'client_name' => $this->faker->company(),
            'selected_fields' => ['CallerName', 'CallerNumber', 'Message'],
            'filter_field' => null,
            'filter_value' => null,
            'include_call_info' => true,
            'recipients' => null,
            'subject' => 'Message Export',
            'schedule_type' => 'manual',
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 0,
            'schedule_day_of_month' => 1,
            'enabled' => true,
            'last_run_at' => null,
            'next_run_at' => null,
            'timezone' => 'America/New_York',
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'manual',
            'recipients' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function immediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'immediate',
            'schedule_time' => null,
            'schedule_day_of_week' => null,
            'schedule_day_of_month' => null,
            'recipients' => [$this->faker->email],
        ]);
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'hourly',
            'schedule_time' => null,
            'recipients' => [$this->faker->email],
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'daily',
            'schedule_time' => '08:00',
            'recipients' => [$this->faker->email],
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'weekly',
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 1,
            'recipients' => [$this->faker->email],
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'monthly',
            'schedule_time' => '08:00',
            'schedule_day_of_month' => 1,
            'recipients' => [$this->faker->email],
        ]);
    }

    public function withFilter(): static
    {
        return $this->state(fn (array $attributes) => [
            'filter_field' => 'Status',
            'filter_value' => 'Active',
        ]);
    }
}
