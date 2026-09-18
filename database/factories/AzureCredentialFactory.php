<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AzureCredentialSource;
use App\Enums\AzureCredentialType;
use App\Models\AzureCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AzureCredentialFactory extends Factory
{
    protected $model = AzureCredential::class;

    public function definition(): array
    {
        return [
            'key_id' => (string) Str::uuid(),
            'app_object_id' => (string) Str::uuid(),
            'app_client_id' => (string) Str::uuid(),
            'app_name' => $this->faker->company().' API',
            'source' => AzureCredentialSource::Application,
            'cred_type' => AzureCredentialType::Secret,
            'cred_name' => $this->faker->randomElement([null, 'Rotated '.$this->faker->year()]),
            'hint' => Str::random(3),
            'start_utc' => Carbon::now()->subMonths(6),
            'end_utc' => Carbon::now()->addMonths(6),
            'last_seen_utc' => Carbon::now(),
            'acknowledged' => false,
        ];
    }

    /**
     * Expiring in $days days -- the axis every test in this area varies.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (): array => [
            // Half a day in, so the row exercises the same flooring the status
            // bands and the alerter use rather than landing exactly on a boundary.
            'end_utc' => Carbon::now()->addDays($days)->addHours(12),
        ]);
    }

    public function expired(int $daysAgo = 5): static
    {
        return $this->state(fn (): array => [
            'end_utc' => Carbon::now()->subDays($daysAgo),
        ]);
    }

    public function certificate(): static
    {
        return $this->state(fn (): array => [
            'cred_type' => AzureCredentialType::Certificate,
            'hint' => strtoupper(bin2hex(random_bytes(20))),
        ]);
    }

    public function servicePrincipal(): static
    {
        return $this->state(fn (): array => [
            'source' => AzureCredentialSource::ServicePrincipal,
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (): array => [
            'acknowledged' => true,
            'acknowledged_at' => Carbon::now(),
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'removed_at' => Carbon::now(),
            'last_seen_utc' => Carbon::now()->subDays(2),
        ]);
    }
}
