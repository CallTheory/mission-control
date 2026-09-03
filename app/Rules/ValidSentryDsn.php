<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Sentry\Dsn;
use Throwable;

/**
 * Validates a GlitchTip/Sentry DSN.
 *
 * GlitchTip DSNs look like https://<publicKey>@glitchtip.example.com/<projectId>
 * — there is no `oNNN.ingest.` subdomain, so nothing here may assume the
 * Sentry-hosted shape.
 */
class ValidSentryDsn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        try {
            $dsn = Dsn::createFromString((string) $value);
        } catch (Throwable) {
            $fail('The :attribute is not a valid DSN. Expected https://key@host/projectId.');

            return;
        }

        // The DSN is transmitted on every event; refuse plaintext outside local
        // development so a misconfiguration cannot leak the key over the wire.
        if ($dsn->getScheme() !== 'https' && ! app()->environment('local')) {
            $fail('The :attribute must use https outside local development.');
        }
    }
}
