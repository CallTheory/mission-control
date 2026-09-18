<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company,
            'user_id' => User::factory(),
            'personal_team' => true,
            // Mirrors the state every pre-existing team was backfilled to: no
            // allow-lists, deliberately unrestricted. Tests that care about the
            // unconfigured state set this to false explicitly.
            'unrestricted_accounts' => true,
        ];
    }
}
