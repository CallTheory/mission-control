<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Enums\FaxProvider;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;

/**
 * Works out which spool sources should be scanned.
 *
 * A lane is a *source*, not a (source, provider) pair. The provider used to be the name
 * of the directory, so it looked like a dimension of the spool; it is now resolved per
 * fax by FaxRouter. Treating it as a dimension here would make an unpinned source scan
 * the same tosend/ once per provider and submit every fax twice.
 *
 * Sites run several Intelligent Series servers but only one processes faxes at a time;
 * the rest sit reachable with an empty tosend/. We never try to work out which is active.
 * Every enabled source is offered up every cycle and whichever is producing files is the
 * live one — fan-out, not failover. There is deliberately no substitute-source concept
 * here for anyone to reach for later.
 *
 * This class touches the database and config only. It must never stat, glob or otherwise
 * reach the filesystem: the entire point of scanning in queued jobs is that a dead mount
 * can only block a disposable worker, and a dispatcher that touched the spool would hand
 * that hang straight back to the scheduler.
 */
class FaxLaneRegistry
{
    /**
     * @param  array<int, string>  $sourceKeys  empty for every enabled source
     * @param  array<int, string>  $providers  restrict to sources that can send via these
     * @return array<int, FaxSpoolSource>
     */
    public function enabled(array $sourceKeys = [], array $providers = []): array
    {
        $datasource = DataSource::first();

        if ($datasource === null) {
            return [];
        }

        $usable = $this->configuredProviders($datasource, $providers);

        if ($usable === []) {
            return [];
        }

        return FaxSpoolSource::query()
            ->enabled()
            ->when($sourceKeys !== [], fn ($query) => $query->whereIn('key', $sourceKeys))
            ->orderBy('key')
            ->get()
            // A source pinned to one provider is only worth scanning when that provider
            // is usable; an unpinned one can route to any of them.
            ->filter(fn (FaxSpoolSource $source): bool => $source->pinned_provider === null
                || in_array($source->pinned_provider, $usable, true))
            ->values()
            ->all();
    }

    /**
     * The providers that are switched on and actually configured.
     *
     * @param  array<int, string>  $only
     * @return array<int, FaxProvider>
     */
    public function configuredProviders(?DataSource $datasource = null, array $only = []): array
    {
        $datasource ??= DataSource::first();

        if ($datasource === null) {
            return [];
        }

        return array_values(array_filter(
            FaxProvider::cases(),
            fn (FaxProvider $provider): bool => ($only === [] || in_array($provider->value, $only, true))
                && $this->isConfigured($datasource, $provider)
        ));
    }

    public function isConfigured(DataSource $datasource, FaxProvider $provider): bool
    {
        return match ($provider) {
            // Only an API key is required for mFax.
            FaxProvider::Mfax => $datasource->mfax_api_key !== null,
            FaxProvider::RingCentral => (bool) ($datasource->ringcentral_client_id
                && $datasource->ringcentral_client_secret
                && $datasource->ringcentral_jwt_token
                && $datasource->ringcentral_api_endpoint),
        };
    }
}
