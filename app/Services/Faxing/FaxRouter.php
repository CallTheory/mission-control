<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Enums\FaxProvider;
use App\Models\DataSource;
use App\Models\FaxProviderPin;
use App\Models\FaxSpoolSource;

/**
 * Decides which provider a fax goes out through.
 *
 * This is the piece that takes the choice away from Intelligent Series. IS allows exactly
 * one output path per fax service, so while the provider was the name of the spool
 * directory, changing provider meant an IS Supervisor change on the customer's server.
 * Now the spool is provider-agnostic and the decision is made here.
 *
 * Resolution runs most specific first:
 *
 *   1. a pin on the recipient's fax number   — "this destination is bad on that provider"
 *   2. a pin on the Intelligent Series account — "this client goes out through that one"
 *   3. the source's pinned provider            — the legacy provider-named directories
 *   4. the system default
 *
 * Pins deliberately outrank a source's pinned provider: a pin is an explicit operator
 * override made in response to a problem, and silently ignoring it for a fax that arrived
 * in the legacy mfax/ directory would make it look broken. The admin screen shows the
 * resolved provider for a number or account so this is visible rather than surprising.
 */
class FaxRouter
{
    public function __construct(private readonly FaxLaneRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $fax  a parsed .fs file
     * @param  (callable(): ?string)|null  $resolveAccount  called only if an account pin
     *                                                      could actually match
     */
    public function route(array $fax, ?FaxSpoolSource $source = null, ?callable $resolveAccount = null): FaxRoute
    {
        $datasource = DataSource::first();
        $usable = $this->registry->configuredProviders($datasource);

        if ($numberPin = FaxProviderPin::forNumber((string) ($fax['phone'] ?? ''))) {
            if (in_array($numberPin->provider, $usable, true)) {
                return new FaxRoute($numberPin->provider, 'pinned by number', $numberPin->allow_failover);
            }
        }

        // Resolving the account means a query against the Intelligent Series database.
        // Most installs have no account pins at all, so check that cheaply first rather
        // than paying for a cross-database lookup on every single fax.
        if ($resolveAccount !== null && $this->hasAccountPins()) {
            if ($accountPin = FaxProviderPin::forAccount($resolveAccount())) {
                if (in_array($accountPin->provider, $usable, true)) {
                    return new FaxRoute($accountPin->provider, 'pinned by account', $accountPin->allow_failover);
                }
            }
        }

        $failover = (bool) ($datasource->fax_failover_enabled ?? false);

        // The legacy directories. A source pinned to a provider is that provider's spool,
        // and always was.
        if ($source?->pinned_provider !== null && in_array($source->pinned_provider, $usable, true)) {
            return new FaxRoute($source->pinned_provider, 'spool source', $failover);
        }

        $default = FaxProvider::tryFromKey($datasource?->fax_default_provider);

        if ($default !== null && in_array($default, $usable, true)) {
            return new FaxRoute($default, 'system default', $failover);
        }

        // Nothing chosen anywhere: use whatever is actually configured, preferring the
        // provider cloud faxing shipped with.
        $fallback = in_array(FaxProvider::fallback(), $usable, true)
            ? FaxProvider::fallback()
            : ($usable[0] ?? FaxProvider::fallback());

        return new FaxRoute($fallback, 'only configured provider', $failover);
    }

    private function hasAccountPins(): bool
    {
        return FaxProviderPin::query()
            ->enabled()
            ->where('match_type', FaxProviderPin::MATCH_ACCOUNT)
            ->exists();
    }

    /**
     * The provider to try after a failed submission, or null to give up and report the
     * failure back to Intelligent Series.
     *
     * @param  array<int, string>  $alreadyTried
     */
    public function nextProvider(FaxRoute $route, array $alreadyTried): ?FaxProvider
    {
        if (! $route->allowFailover) {
            return null;
        }

        $datasource = DataSource::first();

        if (! ($datasource->fax_failover_enabled ?? false)) {
            return null;
        }

        foreach ($this->registry->configuredProviders($datasource) as $provider) {
            if (! in_array($provider->value, $alreadyTried, true)) {
                return $provider;
            }
        }

        return null;
    }
}
