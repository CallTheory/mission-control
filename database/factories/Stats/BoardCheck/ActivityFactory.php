<?php

declare(strict_types=1);

namespace Database\Factories\Stats\BoardCheck;

use App\Models\Stats\BoardCheck\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * The board check activity log: who did what to which message.
     *
     * The column list is the `activities` table as migrated -- user_id, activity_type and
     * the nullable msgId added later. The model carries no $table, so it resolves to
     * `activities` by Eloquent's convention rather than to `board_check_activities`.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_type' => $this->faker->randomElement([
                'Message Reviewed',
                'Message Approved',
                'Problem Verified',
            ]),
            'msgId' => $this->faker->numberBetween(100000, 999999),
        ];
    }
}
