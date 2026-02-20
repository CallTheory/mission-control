<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DataSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

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

        $dataSource = DataSource::where('type', 'twilio')
            ->where('enabled', true)
            ->first();

        if (! $dataSource) {
            Log::warning('Twilio signature validation skipped: no active Twilio data source');

            return $next($request);
        }

        $authToken = $dataSource->credentials['auth_token'] ?? null;
        if (! $authToken) {
            Log::warning('Twilio signature validation skipped: no auth token configured');

            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature', '');
        if (empty($signature)) {
            Log::warning('Twilio request rejected: missing X-Twilio-Signature header');

            return response('Forbidden', 403);
        }

        $validator = new RequestValidator($authToken);
        $url = $request->fullUrl();
        $params = $request->all();

        if (! $validator->validate($signature, $url, $params)) {
            Log::warning('Twilio request rejected: invalid signature', [
                'url' => $url,
            ]);

            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
