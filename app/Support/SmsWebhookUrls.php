<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SmsProvider;
use Illuminate\Support\Facades\Route;

/**
 * The URLs a carrier posts back to: shown on the System screen so they can be
 * pasted into a carrier portal, and handed to Twilio per message as its status
 * callback.
 *
 * Twilio keeps the original unprefixed paths (`/wctp/sms/incoming`,
 * `/wctp/callback/{id}`) so consoles configured before the other carriers existed
 * keep working. Bandwidth and Com.io use provider-scoped paths, and for them both
 * URLs accept both event kinds -- Bandwidth's messaging application posts inbound
 * messages and delivery receipts to a single configured URL, so either path has to
 * handle whatever arrives.
 *
 * Route names are used when registered and literal paths otherwise: the WCTP routes
 * sit behind a system feature flag read at route-registration time, so they are
 * absent from the route table when the gateway is switched off (and in tests that
 * enable the flag mid-run), where route() would throw instead of producing the URL
 * the UI wants to display.
 */
final class SmsWebhookUrls
{
    public static function inbound(SmsProvider $provider): string
    {
        if ($provider === SmsProvider::Twilio) {
            return Route::has('wctp.sms.incoming')
                ? route('wctp.sms.incoming')
                : url('/wctp/sms/incoming');
        }

        return Route::has('wctp.sms.provider.incoming')
            ? route('wctp.sms.provider.incoming', ['provider' => $provider->value])
            : url("/wctp/sms/{$provider->value}/incoming");
    }

    /**
     * The delivery-receipt URL.
     *
     * Twilio takes one per message, so the WCTP message id goes in the path. The
     * other carriers configure a single URL in their portal and identify the message
     * inside the payload, so they get the path without an id.
     */
    public static function status(SmsProvider $provider, ?string $wctpMessageId = null): string
    {
        if ($provider === SmsProvider::Twilio) {
            // A placeholder rather than a real id when this is for display: route()
            // would percent-encode the braces into noise.
            if ($wctpMessageId === null) {
                return rtrim(self::status($provider, 'x'), 'x').'{messageId}';
            }

            return Route::has('wctp.callback')
                ? route('wctp.callback', ['messageId' => $wctpMessageId])
                : url('/wctp/callback/'.rawurlencode($wctpMessageId));
        }

        $parameters = ['provider' => $provider->value];

        if ($wctpMessageId !== null) {
            $parameters['messageId'] = $wctpMessageId;
        }

        return Route::has('wctp.provider.callback')
            ? route('wctp.provider.callback', $parameters)
            : url("/wctp/{$provider->value}/callback".($wctpMessageId === null ? '' : '/'.rawurlencode($wctpMessageId)));
    }
}
