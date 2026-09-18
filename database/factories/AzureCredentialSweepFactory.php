<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AzureCredentialSweep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AzureCredentialSweepFactory extends Factory
{
    protected $model = AzureCredentialSweep::class;

    public function definition(): array
    {
        return [
            'started_at' => Carbon::now()->subMinutes(5),
            'finished_at' => Carbon::now()->subMinutes(4),
            'status' => AzureCredentialSweep::STATUS_SUCCESS,
            'applications' => 12,
            'service_principals' => 20,
            'credentials_seen' => 30,
            'credentials_added' => 0,
            'credentials_removed' => 0,
        ];
    }

    public function failed(string $error = 'Entra rejected the credentials.'): static
    {
        return $this->state(fn (): array => [
            'status' => AzureCredentialSweep::STATUS_FAILED,
            'error' => $error,
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn (): array => [
            'started_at' => Carbon::now()->subHours(AzureCredentialSweep::STALE_AFTER_HOURS + 2),
            'finished_at' => Carbon::now()->subHours(AzureCredentialSweep::STALE_AFTER_HOURS + 2),
        ]);
    }
}
