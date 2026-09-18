<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SmsProvider;
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
        $sid = in_array($status, ['pending', 'queued']) ? null : 'SM'.Str::random(32);

        return [
            'enterprise_host_id' => EnterpriseHost::factory(),
            'to' => $this->faker->phoneNumber(),
            'from' => '+15551234567',
            'message' => $this->faker->sentence(),
            'wctp_message_id' => 'wctp_'.Str::random(10),
            'twilio_sid' => $sid,
            'provider' => SmsProvider::Twilio->value,
            'provider_message_id' => $sid,
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
            'provider_message_id' => null,
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
            'provider_message_id' => null,
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
            ...$this->carrierIds(),
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
            ...$this->carrierIds(),
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
            ...$this->carrierIds(),
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

    /**
     * A message that went out through a carrier other than Twilio, which is what
     * `twilio_sid` being null while `provider_message_id` is set looks like.
     */
    public function provider(SmsProvider $provider): static
    {
        return $this->state(fn () => [
            'provider' => $provider->value,
            'twilio_sid' => $provider === SmsProvider::Twilio ? 'SM'.Str::random(32) : null,
            'provider_message_id' => match ($provider) {
                SmsProvider::Twilio => 'SM'.Str::random(32),
                SmsProvider::Bandwidth => Str::random(28),
                SmsProvider::Commio => (string) Str::uuid(),
            },
        ]);
    }

    /**
     * The carrier's id for a message that has actually been accepted. Twilio's SID
     * is written to both columns; see WctpMessage::markAsSent().
     *
     * @return array<string, string>
     */
    private function carrierIds(): array
    {
        $sid = 'SM'.Str::random(32);

        return [
            'twilio_sid' => $sid,
            'provider_message_id' => $sid,
        ];
    }
}
