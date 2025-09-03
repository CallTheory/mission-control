<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WctpMessage;
use App\Models\EnterpriseHost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WctpMessageFactory extends Factory
{
    protected $model = WctpMessage::class;

    public function definition(): array
    {
        $statuses = ['pending', 'sent', 'delivered', 'failed'];
        $status = $this->faker->randomElement($statuses);

        return [
            'enterprise_host_id' => EnterpriseHost::factory(),
            'to' => $this->faker->phoneNumber(),
            'from' => '+15551234567',
            'message' => $this->faker->sentence(),
            'wctp_message_id' => 'wctp_' . Str::random(10),
            'twilio_sid' => $status !== 'pending' ? 'SM' . Str::random(32) : null,
            'direction' => 'outbound',
            'status' => $status,
            'delivered_at' => $status === 'delivered' ? $this->faker->dateTimeBetween('-30 minutes') : null,
            'failed_at' => $status === 'failed' ? $this->faker->dateTimeBetween('-30 minutes') : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'twilio_sid' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'twilio_sid' => 'SM' . Str::random(32),
            'delivered_at' => null,
            'failed_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'twilio_sid' => 'SM' . Str::random(32),
            'delivered_at' => now(),
            'failed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'twilio_sid' => 'SM' . Str::random(32),
            'delivered_at' => null,
            'failed_at' => now(),
        ]);
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'inbound',
            'to' => '+15551234567', // Our number
            'from' => $this->faker->phoneNumber(), // Customer number
        ]);
    }
}