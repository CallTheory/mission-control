<?php

declare(strict_types=1);

namespace App\Services\Azure;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only Microsoft Graph access for the token watcher.
 *
 * Authenticates with the client credentials flow -- an application identity, not a
 * user -- and only ever issues GETs. The app registration behind it holds one
 * permission, Application.Read.All, so nothing here can change anything in Azure
 * even if it tried.
 *
 * The one Graph subtlety worth knowing: /applications is paged, and a tenant with
 * more than a hundred app registrations returns an @odata.nextLink that MUST be
 * followed. Stopping at the first page silently under-reports expiries, which is
 * the one failure this dashboard cannot have.
 */
class GraphClient
{
    public const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public const LOGIN_BASE = 'https://login.microsoftonline.com';

    /** The credential fields the sweep needs, and nothing else. */
    private const SELECT = 'id,appId,displayName,passwordCredentials,keyCredentials';

    private const PAGE_SIZE = 100;

    /** Seconds shaved off a token's lifetime, so one never expires mid-sweep. */
    private const TOKEN_SKEW = 120;

    private ?GraphCredentials $credentials;

    public function __construct(?GraphCredentials $credentials = null)
    {
        $this->credentials = $credentials;
    }

    public function credentials(): GraphCredentials
    {
        return $this->credentials ??= GraphCredentials::fromDataSource();
    }

    /**
     * Every app registration in the tenant, one page at a time.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws GraphException
     */
    public function applications(): Generator
    {
        yield from $this->paged('/applications');
    }

    /**
     * Every service principal in the tenant.
     *
     * Swept alongside applications because service principals carry credentials of
     * their own -- SAML signing certificates most commonly -- that never appear
     * under /applications.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws GraphException
     */
    public function servicePrincipals(): Generator
    {
        yield from $this->paged('/servicePrincipals');
    }

    /**
     * How many app registrations the tenant has, for the connection test.
     *
     * Null when Graph declines to count them; the test then reports only that
     * authentication and the read permission work, which is the point of it.
     *
     * @throws GraphException
     */
    public function applicationCount(): ?int
    {
        $response = $this->get(
            self::GRAPH_BASE.'/applications?$select=id&$top=1&$count=true',
            // Counting an unfiltered collection is an "advanced query", which Graph
            // only serves against its eventually-consistent index.
            ['ConsistencyLevel' => 'eventual'],
        );

        // Read off the decoded array rather than with json('@odata.count'):
        // dot-notation lookup would treat the key's own dot as a nesting level.
        $count = $response->json()['@odata.count'] ?? null;

        return is_int($count) ? $count : null;
    }

    /**
     * Follows @odata.nextLink until Graph stops sending one.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws GraphException
     */
    private function paged(string $path): Generator
    {
        $url = self::GRAPH_BASE.$path.'?$select='.self::SELECT.'&$top='.self::PAGE_SIZE;

        while ($url !== null) {
            $body = $this->get($url)->json();

            foreach ($body['value'] ?? [] as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $next = $body['@odata.nextLink'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
        }
    }

    /**
     * @param  array<string, string>  $headers
     *
     * @throws GraphException
     */
    private function get(string $url, array $headers = []): Response
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->withHeaders($headers)
                ->timeout(30)
                ->connectTimeout(10)
                // 429 and 503 from Graph are throttling, not failure. A daily sweep
                // can afford to wait rather than report a tenant it never read.
                ->retry(3, 2000, function (Throwable $e): bool {
                    if ($e instanceof ConnectionException) {
                        return true;
                    }

                    return $e instanceof RequestException
                        && in_array($e->response->status(), [429, 503, 504], true);
                }, throw: false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new GraphException('Could not reach Microsoft Graph: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            // Thrown only when a retryable status kept coming back until the
            // attempts ran out; everything else arrives as a failed response.
            throw new GraphException($this->describeFailure($e->response), 0, $e);
        }

        if ($response->failed()) {
            throw new GraphException($this->describeFailure($response));
        }

        return $response;
    }

    /**
     * A bearer token for Graph, cached for the rest of its lifetime.
     *
     * @throws GraphException
     */
    public function accessToken(): string
    {
        $credentials = $this->credentials();
        $credentials->requireConfigured();

        $key = 'azure-graph-token:'.$credentials->fingerprint();

        try {
            $cached = Cache::get($key);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (Throwable) {
            // Cache unreachable: fetch a fresh token rather than fail the sweep.
            return $this->fetchToken()['token'];
        }

        $fresh = $this->fetchToken();

        try {
            Cache::put($key, $fresh['token'], $fresh['ttl']);
        } catch (Throwable) {
            // Same again: a token we cannot cache is still a usable token.
        }

        return $fresh['token'];
    }

    /**
     * Drops the cached token. Called when credentials are saved, so a rotated
     * secret takes effect immediately instead of up to an hour later.
     */
    public function forgetToken(): void
    {
        try {
            Cache::forget('azure-graph-token:'.$this->credentials()->fingerprint());
        } catch (Throwable) {
            // Nothing to do: an uncacheable token was never cached.
        }
    }

    /**
     * @return array{token: string, ttl: int}
     *
     * @throws GraphException
     */
    private function fetchToken(): array
    {
        $credentials = $this->credentials();
        $credentials->requireConfigured();

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->connectTimeout(10)
                ->post(self::LOGIN_BASE.'/'.$credentials->tenantId.'/oauth2/v2.0/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $credentials->clientId,
                    'client_secret' => $credentials->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);
        } catch (ConnectionException $e) {
            throw new GraphException('Could not reach the Entra token endpoint: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new GraphException($this->describeTokenFailure($response));
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new GraphException('The Entra token endpoint returned no access token.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);

        return [
            'token' => $token,
            'ttl' => max(60, $expiresIn - self::TOKEN_SKEW),
        ];
    }

    /**
     * Turns a Graph error body into something an administrator can act on.
     */
    private function describeFailure(Response $response): string
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message');

        $detail = trim(implode(' ', array_filter([
            is_string($code) ? '['.$code.']' : null,
            is_string($message) ? $message : null,
        ])));

        if ($response->status() === 403) {
            return 'Microsoft Graph refused the request (403). The app registration is '
                .'missing the Application.Read.All application permission, or admin '
                .'consent has not been granted. '.$detail;
        }

        return 'Microsoft Graph returned HTTP '.$response->status().'. '
            .($detail !== '' ? $detail : 'No error detail was supplied.');
    }

    /**
     * Same for the token endpoint, whose errors are a different shape.
     */
    private function describeTokenFailure(Response $response): string
    {
        $error = $response->json('error');
        $description = $response->json('error_description');

        // AADSTS7000222 is specifically "the client secret has expired", which for
        // this application means the watcher has outlived its own secret.
        if (is_string($description) && str_contains($description, 'AADSTS7000222')) {
            return 'The client secret for the token watcher itself has expired. Create a '
                .'new secret on the app registration and save it on System -> Integrations.';
        }

        return 'Entra rejected the credentials (HTTP '.$response->status().'). '
            .(is_string($description) ? $description : (is_string($error) ? $error : ''));
    }
}
