<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScreenCapture;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use App\Support\CallAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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

        // Turned away before the switch-DB lookup when no call could be allowed.
        CallAccess::authorizeTeam($request->user());

        try {
            $call = new Call(['ISCallId' => $isCallID]);
        } catch (Exception $e) {
            abort(400);
        }

        CallAccess::authorizeCall($request->user(), $call);

        $screenCapture = Redis::get("{$isCallID}.mp4");

        if (is_null($screenCapture)) {
            if (Cache::has("screencapture_unavailable:{$isCallID}")) {
                abort(404);
            }

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
