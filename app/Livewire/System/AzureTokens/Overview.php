<?php

declare(strict_types=1);

namespace App\Livewire\System\AzureTokens;

use App\Enums\AzureCredentialStatus;
use App\Enums\Capability;
use App\Jobs\SweepAzureCredentials;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use App\Services\Azure\GraphCredentials;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The header of the token dashboard: the counts, the state of the last sweep, and
 * the manual sweep button.
 *
 * The stale-sweep warning is the important part of this component. Every other
 * number on the page is only as true as the last sweep, so a collector that has
 * quietly stopped -- most likely because the watcher's own client secret lapsed --
 * would otherwise render as a tenant with nothing expiring.
 */
class Overview extends Component
{
    use AuthorizesSystemComponent;

    public bool $sweepQueued = false;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAzureTokens;
    }

    /**
     * @return array<string, int>
     */
    public function getStatsProperty(): array
    {
        return [
            'total' => AzureCredential::query()->present()->count(),
            'expiring' => AzureCredential::query()
                ->present()
                ->expiringWithin(AzureCredentialStatus::WARNING_DAYS)
                ->count(),
            'expired' => AzureCredential::query()
                ->present()
                ->status(AzureCredentialStatus::Expired)
                ->count(),
            'acknowledged' => AzureCredential::query()->present()->where('acknowledged', true)->count(),
        ];
    }

    public function getLastSweepProperty(): ?AzureCredentialSweep
    {
        return AzureCredentialSweep::mostRecent();
    }

    public function getLastSuccessfulSweepProperty(): ?AzureCredentialSweep
    {
        return AzureCredentialSweep::mostRecentSuccessful();
    }

    public function getIsStaleProperty(): bool
    {
        return AzureCredentialSweep::isStale();
    }

    public function getCredentialsConfiguredProperty(): bool
    {
        return GraphCredentials::fromDataSource()->configured();
    }

    public function getSweepsEnabledProperty(): bool
    {
        return GraphCredentials::fromDataSource()->enabled;
    }

    /**
     * Queue a sweep now rather than waiting for the scheduler, which is what an
     * administrator wants immediately after fixing credentials.
     */
    public function sweepNow(): void
    {
        $this->authorize($this->requiredCapability()->value);

        if (! GraphCredentials::fromDataSource()->configured()) {
            return;
        }

        // The job is unique for the hour, so a second press while one is still
        // running is a no-op rather than two passes over the same rows.
        SweepAzureCredentials::dispatch();

        $this->sweepQueued = true;
    }

    #[On('azure-credentials-updated')]
    public function refresh(): void
    {
        // The computed properties are read fresh on every render; acknowledging a
        // credential in the table below only needs to trigger one.
    }

    public function render(): View
    {
        return view('livewire.system.azure-tokens.overview');
    }
}
