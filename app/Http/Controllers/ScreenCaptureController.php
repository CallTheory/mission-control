<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScreenCapture;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class ScreenCaptureController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request, int $isCallID): Response
    {
        if (! Helpers::isSystemFeatureEnabled('screencaptures')) {
            abort(404);
        }

        try {
            $call = new Call(['ISCallId' => $isCallID]);
        } catch (Exception $e) {
            abort(400);
        }

        if (Helpers::allowedAccountAccess(
            $call->ClientNumber,
            $call->BillingCode ?? '',
            $request->user()->currentTeam->allowed_accounts ?? '',
            $request->user()->currentTeam->allowed_billing ?? ''
        ) !== true) {
            abort(403);
        }

        $screenCapture = Redis::get("{$isCallID}.mp4");

        if (is_null($screenCapture)) {
            ProcessScreenCapture::dispatch($isCallID);

            return response()->noContent(202);
        }

        $size = strlen($screenCapture);

        $headers = [
            'Content-Type' => 'video/mp4',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
        ];

        return response($screenCapture, 200, $headers);

    }
}
