<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\DataSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\Platform\Platform;
use RingCentral\SDK\SDK as RingCentralSDK;
use RuntimeException;
use Throwable;

/**
 * One authenticated RingCentral platform per process, backed by a shared access
 * token in Redis.
 *
 * Every caller used to build its own SDK and call `$platform->login(['jwt' => ...])`,
 * which meant one request to /restapi/oauth/token per fax sent, per dashboard
 * rebuild (every minute), and two per resend from the UI. RingCentral throttles the
 * auth endpoints separately from — and far more tightly than — the fax endpoints, so
 * a busy period spent most of its quota just logging in, and the 429s that came back
 * were counted as fax failures.
 *
 * An access token is good for an hour and is account-wide, so it is cached and shared:
 * a burst of 200 faxes now costs one token request instead of 200. The refresh token
 * (a week) is used ahead of a fresh JWT login when the access token expires.
 */
class RingCentralClient
{
    /**
     * Refresh this many seconds before the token actually expires, so a long-running
     * request can't start with a token that dies mid-flight.
     */
    private const EXPIRY_SKEW_SECONDS = 120;

    /**
     * Ceiling on how long the cached payload lives, independent of the refresh token's
     * own week-long lifetime.
     */
    private const MAX_CACHE_SECONDS = 604800;

    /**
     * Container key a test can bind a Guzzle client to, so the SDK talks to a mock.
     */
    public const GUZZLE_BINDING = 'faxing.ringcentral.guzzle';

    private ?RingCentralSDK $sdk = null;

    /**
     * @param  string|null  $jwt  authenticate as this JWT's user instead of the data
     *                            source's — how faxes are sent through additional
     *                            extensions. The app credentials stay the data source's.
     */
    public function __construct(private readonly DataSource $datasource, private readonly ?string $jwt = null) {}

    public static function forDataSource(?DataSource $datasource = null): self
    {
        return new self($datasource ?? DataSource::firstOrFail());
    }

    /**
     * A client authenticated as one particular sending extension. Each extension gets its
     * own cached token, because the token cache key includes the JWT.
     */
    public static function forExtension(DataSource $datasource, RingCentralExtension $extension): self
    {
        return new self($datasource, $extension->jwt);
    }

    /**
     * True when there are enough credentials on the data source to attempt a login.
     */
    public function configured(): bool
    {
        return filled($this->datasource->ringcentral_client_id)
            && filled($this->datasource->ringcentral_client_secret)
            && filled($this->jwt())
            && filled($this->datasource->ringcentral_api_endpoint);
    }

    /**
     * An authenticated SDK instance — use this when you need createMultipartBuilder().
     *
     * @throws ApiException
     */
    public function sdk(): RingCentralSDK
    {
        $this->authenticate();

        return $this->sdk;
    }

    /**
     * @throws ApiException
     */
    public function platform(): Platform
    {
        return $this->sdk()->platform();
    }

    /**
     * The RingCentral extension this client is authenticated as — the token's owner_id.
     *
     * Rate limits are counted per authenticated user (extension), so this is the identity
     * that matters when reading X-Rate-Limit-* headers. Free to call: it comes from the
     * token we already hold, not from an API request. Null before authentication.
     */
    public function extensionId(): ?string
    {
        $owner = $this->sdk?->platform()->auth()->data()['owner_id'] ?? null;

        return $owner === null || $owner === '' ? null : (string) $owner;
    }

    /**
     * Drop the shared token. Call this after a 401 so the next caller re-authenticates
     * instead of replaying a token the API has already rejected.
     */
    public function forgetToken(): void
    {
        Redis::del($this->cacheKey());

        $this->sdk = null;
    }

    /**
     * Ensure $this->sdk holds a platform with a usable access token, reusing the shared
     * one from Redis wherever possible.
     *
     * @throws ApiException
     */
    private function authenticate(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('RingCentral credentials are not configured.');
        }

        $this->sdk ??= new RingCentralSDK(
            $this->datasource->ringcentral_client_id,
            $this->datasource->ringcentral_client_secret,
            $this->datasource->ringcentral_api_endpoint,
            '',
            '',
            // Tests bind a Guzzle client backed by a MockHandler here; in production
            // nothing is bound and the SDK builds its own.
            app()->bound(self::GUZZLE_BINDING) ? app(self::GUZZLE_BINDING) : null,
        );

        $platform = $this->sdk->platform();

        if ($this->restoreCachedToken($platform)) {
            return;
        }

        // Serialize the refresh so a burst of workers that all found an expired token
        // produces one token request rather than one per worker. block() waits for
        // whichever worker got there first, then the cache re-check below short-circuits.
        $lock = Cache::lock($this->cacheKey().':lock', 15);

        try {
            $lock->block(10);
        } catch (Throwable) {
            // Couldn't get the lock in time; fall through and authenticate directly
            // rather than failing the fax over lock contention.
            $this->establishToken($platform);

            return;
        }

        try {
            if ($this->restoreCachedToken($platform)) {
                return;
            }

            $this->establishToken($platform);
        } finally {
            $lock->release();
        }
    }

    /**
     * Load the shared token into the platform. Returns false when there is nothing
     * cached or what is cached has expired.
     */
    private function restoreCachedToken(Platform $platform): bool
    {
        $cached = $this->readCache();

        if ($cached === null) {
            return false;
        }

        $platform->auth()->setData($cached);

        return $this->tokenUsable($platform);
    }

    /**
     * Refresh the shared token, or perform a fresh JWT login when refreshing isn't
     * possible. The result is written back to Redis for every other caller.
     *
     * @throws ApiException
     */
    private function establishToken(Platform $platform): void
    {
        if ($platform->auth()->refreshTokenValid()) {
            try {
                $platform->refresh();
                $this->writeCache($platform);

                return;
            } catch (Throwable $e) {
                Log::warning('RingCentralClient: token refresh failed, falling back to JWT login: '.$e->getMessage());
                $platform->auth()->reset();
            }
        }

        $platform->login(['jwt' => $this->jwt()]);

        $this->writeCache($platform);
    }

    /**
     * Treat a token that expires within the skew window as already expired.
     */
    private function tokenUsable(Platform $platform): bool
    {
        $data = $platform->auth()->data();

        if (empty($data['access_token'])) {
            return false;
        }

        return ((int) ($data['expire_time'] ?? 0)) - self::EXPIRY_SKEW_SECONDS > time();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(): ?array
    {
        $payload = Redis::get($this->cacheKey());

        if ($payload === null) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('RingCentralClient: discarding unreadable cached token: '.$e->getMessage());

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(Platform $platform): void
    {
        $data = $platform->auth()->data();

        // Hold the payload only as long as the refresh token can still renew it.
        $ttl = ((int) ($data['refresh_token_expire_time'] ?? 0)) - time();
        $ttl = min(max($ttl, (int) ($data['expires_in'] ?? 0), 60), self::MAX_CACHE_SECONDS);

        // The payload carries a live access token, so it is encrypted at rest for the
        // same reason the fax jobs are ShouldBeEncrypted.
        Redis::setEx($this->cacheKey(), $ttl, Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    /**
     * Fingerprint the credentials into the key so rotating the client id or JWT in
     * System → Integrations misses the old token instead of replaying it.
     */
    private function cacheKey(): string
    {
        return self::tokenCacheKey(
            (string) $this->datasource->ringcentral_client_id,
            (string) $this->jwt(),
            (string) $this->datasource->ringcentral_api_endpoint,
        );
    }

    /**
     * Unchanged for the primary extension, so the token already cached in production keeps
     * being used across the deploy.
     */
    public static function tokenCacheKey(string $clientId, string $jwt, string $endpoint): string
    {
        $fingerprint = substr(hash('sha256', implode('|', [$clientId, $jwt, $endpoint])), 0, 16);

        return "cloud-faxing:ringcentral:auth:{$fingerprint}";
    }

    private function jwt(): ?string
    {
        return $this->jwt ?? $this->datasource->ringcentral_jwt_token;
    }
}
