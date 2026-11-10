<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Services\Faxing\RingCentralClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\RequestInterface;

/**
 * Drives the real RingCentral SDK against canned HTTP responses.
 *
 * RingCentralClient and FaxAccountLookup both talk to Redis directly (not through the
 * cache), and the test sandbox has no Redis. So Redis is mocked to hand back a valid,
 * cached access token — no login request is ever made — plus a cached Intelligent Series
 * account, so the lookup never reaches for the IS database. Every request the SDK does
 * make lands on the MockHandler, and is recorded in $this->ringCentralRequests.
 */
trait FakesRingCentral
{
    protected MockHandler $ringCentralHttp;

    /** @var array<int, array{request: RequestInterface}> */
    protected array $ringCentralRequests = [];

    /**
     * @param  array<string, string>  $tokenOwners  JWT => extension id the token belongs to
     */
    protected function fakeRingCentral(array $tokenOwners = [], ?string $defaultOwner = '101'): void
    {
        $this->ringCentralHttp = new MockHandler;
        $this->ringCentralRequests = [];

        $stack = HandlerStack::create($this->ringCentralHttp);
        $stack->push(Middleware::history($this->ringCentralRequests));

        $this->app->instance(RingCentralClient::GUZZLE_BINDING, new GuzzleClient(['handler' => $stack]));

        Redis::shouldReceive('get')->andReturnUsing(function (string $key) use ($defaultOwner) {
            if (str_starts_with($key, 'cloud-faxing:ringcentral:auth:')) {
                return Crypt::encryptString(json_encode($this->ringCentralToken($defaultOwner)));
            }

            if (str_starts_with($key, 'cloud-faxing:account:')) {
                return json_encode(['number' => '1544', 'name' => 'Test Client']);
            }

            return null;
        });
        Redis::shouldReceive('setEx')->andReturn(true);
        Redis::shouldReceive('del')->andReturn(1);
    }

    /**
     * Queue a fax API response with the given rate-limit headers.
     *
     * @param  array<string, string>  $headers
     */
    protected function queueRingCentralResponse(int $status, array $headers = [], array $body = []): void
    {
        $body = $body ?: ($status < 400
            ? ['id' => (string) random_int(100000, 999999), 'messageStatus' => 'Queued']
            : ['errorCode' => $status === 429 ? 'CMN-301' : 'CMN-500', 'message' => $status === 429 ? 'Request rate exceeded' : 'Internal error']);

        $this->ringCentralHttp->append(new Response($status, $headers + ['Content-Type' => 'application/json'], json_encode($body)));
    }

    /**
     * @return array<string, string>
     */
    protected function rateLimitHeaders(int $remaining, int $limit = 10, int $window = 60, ?int $retryAfter = null): array
    {
        return array_filter([
            'X-Rate-Limit-Group' => 'heavy',
            'X-Rate-Limit-Limit' => (string) $limit,
            'X-Rate-Limit-Remaining' => (string) $remaining,
            'X-Rate-Limit-Window' => (string) $window,
            'Retry-After' => $retryAfter !== null ? (string) $retryAfter : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringCentralToken(?string $owner): array
    {
        return [
            'token_type' => 'bearer',
            'access_token' => 'test-access-token',
            'expires_in' => 3600,
            'expire_time' => time() + 3600,
            'refresh_token' => 'test-refresh-token',
            'refresh_token_expires_in' => 604800,
            'refresh_token_expire_time' => time() + 604800,
            'scope' => 'Faxes',
            'owner_id' => $owner ?? '',
            'endpoint_id' => 'test-endpoint',
        ];
    }
}
