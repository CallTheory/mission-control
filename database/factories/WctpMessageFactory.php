<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WctpMessageFactory extends Factory
{
    protected $model = WctpMessage::class;

    public function definition(): array
    {
        $statuses = ['pending', 'queued', 'sent', 'delivered', 'failed'];
        $status = $this->faker->randomElement($statuses);

        return [
            'enterprise_host_id' => EnterpriseHost::factory(),
            'to' => $this->faker->phoneNumber(),
            'from' => '+15551234567',
            'message' => $this->faker->sentence(),
            'wctp_message_id' => 'wctp_'.Str::random(10),
            'twilio_sid' => ! in_array($status, ['pending', 'queued']) ? 'SM'.Str::random(32) : null,
            'direction' => 'outbound',
            'status' => $status,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'delivered_at' => $status === 'delivered' ? $this->faker->dateTimeBetween('-30 minutes') : null,
            'failed_at' => $status === 'failed' ? $this->faker->dateTimeBetween('-30 minutes') : null,
            'submitted_at' => in_array($status, ['queued', 'sent', 'delivered', 'failed']) ? $this->faker->dateTimeBetween('-1 hour', '-30 minutes') : null,
            'processed_at' => in_array($status, ['sent', 'delivered', 'failed']) ? $this->faker->dateTimeBetween('-30 minutes') : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'twilio_sid' => null,
            'error_message' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'submitted_at' => null,
            'processed_at' => null,
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => 'queued',
            'twilio_sid' => null,
            'error_message' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'submitted_at' => now(),
            'processed_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'twilio_sid' => 'SM'.Str::random(32),
            'error_message' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'submitted_at' => now()->subMinutes(5),
            'processed_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'twilio_sid' => 'SM'.Str::random(32),
            'error_message' => null,
            'delivered_at' => now(),
            'failed_at' => null,
            'submitted_at' => now()->subMinutes(5),
            'processed_at' => now()->subMinutes(4),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'twilio_sid' => 'SM'.Str::random(32),
            'error_message' => 'Delivery failed: Error 30003',
            'delivered_at' => null,
            'failed_at' => now(),
            'submitted_at' => now()->subMinutes(5),
            'processed_at' => now()->subMinutes(4),
        ]);
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'inbound',
            'to' => '+15551234567',
            'from' => $this->faker->phoneNumber(),
        ]);
    }
}
