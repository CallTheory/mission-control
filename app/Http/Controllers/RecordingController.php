<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallRecording;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class RecordingController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request, int $isCallID): Response
    {
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

        $recording = Redis::get("{$isCallID}.wav");

        if (! $recording) {
            ProcessCallRecording::dispatch($isCallID);

            return response()->noContent(202);
        }

        $headers = [
            'Content-Type' => 'audio/wav',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Length' => strlen($recording),
            'Accept-Ranges' => 'bytes',
        ];

        return response($recording, 200, $headers);
    }
}
