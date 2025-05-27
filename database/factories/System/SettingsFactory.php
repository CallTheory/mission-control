<?php

namespace Database\Factories\System;

use App\Models\System\Settings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settings>
 */
class SettingsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Settings::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'switch_data_timezone' => 'UTC'
        ];
    }
}
