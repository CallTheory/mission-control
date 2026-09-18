<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SmsProvider;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a carrier webhook against whatever that carrier can prove.
 *
 * The provider comes from the route, so one middleware covers every carrier: Twilio
 * verifies its request signature, Bandwidth and Commio present HTTP Basic
 * credentials or a shared secret. Every gateway fails closed when it has no
 * credential to check against.
 *
 * @see SmsGateway::verifyWebhook()
 */
class ValidateSmsProviderWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = SmsProvider::tryFromKey($request->route('provider'));

        if ($provider === null) {
            abort(404);
        }

        if (! config('wctp.validate_provider_webhooks', true)) {
            return $next($request);
        }

        // Twilio's own opt-out still applies on its provider-scoped route, so the
        // two ways of reaching the same handler behave the same.
        if ($provider === SmsProvider::Twilio && ! config('wctp.validate_twilio_signatures', true)) {
            return $next($request);
        }

        if (! app(SmsGatewayManager::class)->gateway($provider)->verifyWebhook($request)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
