<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SmsProvider;
use App\Services\Sms\SmsGatewayManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signature validation for the original, unprefixed Twilio webhook routes.
 *
 * The check itself lives on TwilioGateway now, so the provider-scoped routes and
 * these legacy ones cannot drift apart; this class remains because those routes and
 * their tests name it, and because the Twilio-specific opt-out config is its own.
 *
 * @see ValidateSmsProviderWebhook for the other carriers
 */
class ValidateTwilioRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('wctp.validate_twilio_signatures', true)) {
            return $next($request);
        }

        // Fails closed: with no data source, or no auth token on it, the signature
        // cannot be verified and the request cannot be trusted.
        if (! app(SmsGatewayManager::class)->gateway(SmsProvider::Twilio)->verifyWebhook($request)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
