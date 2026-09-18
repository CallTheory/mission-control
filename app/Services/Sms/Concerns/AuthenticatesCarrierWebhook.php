<?php

declare(strict_types=1);

namespace App\Services\Sms\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook authentication for the carriers that have no request signing.
 *
 * Twilio signs its callbacks, so it verifies the signature instead. Bandwidth can
 * send HTTP Basic credentials configured on the messaging application; Com.io's
 * portal takes a bare URL, so it gets a shared secret in the query string. Both
 * are accepted for either carrier, because which one a portal can actually send
 * is a property of the portal, not of us.
 *
 * Fails closed: with neither credential stored, every request is rejected. That is
 * deliberate -- an unauthenticated inbound endpoint would let anyone inject
 * messages into an enterprise host's queue.
 */
trait AuthenticatesCarrierWebhook
{
    /**
     * The stored Basic auth pair, or nulls when not configured.
     *
     * @return array{0: ?string, 1: ?string}
     */
    abstract protected function callbackBasicCredentials(): array;

    /**
     * The stored shared secret, or null when not configured.
     */
    abstract protected function callbackToken(): ?string;

    public function verifyWebhook(Request $request): bool
    {
        [$username, $password] = $this->callbackBasicCredentials();
        $token = $this->callbackToken();

        $hasBasic = filled($username) && filled($password);
        $hasToken = filled($token);

        if (! $hasBasic && ! $hasToken) {
            Log::warning('Carrier webhook rejected: no callback credentials configured', [
                'provider' => $this->provider()->value,
            ]);

            return false;
        }

        if ($hasBasic
            && hash_equals((string) $username, (string) $request->getUser())
            && hash_equals((string) $password, (string) $request->getPassword())) {
            return true;
        }

        if ($hasToken) {
            $presented = (string) ($request->query('token') ?? $request->header('X-Callback-Token', ''));

            if ($presented !== '' && hash_equals((string) $token, $presented)) {
                return true;
            }
        }

        Log::warning('Carrier webhook rejected: credentials did not match', [
            'provider' => $this->provider()->value,
        ]);

        return false;
    }
}
