<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallRecording;
use App\Models\Stats\Calls\Call;
use App\Support\CallAccess;
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
        // Turned away before the switch-DB lookup when no call could be allowed.
        CallAccess::authorizeTeam($request->user());

        try {
            $call = new Call(['ISCallId' => $isCallID]);
        } catch (Exception $e) {
            abort(400);
        }

        CallAccess::authorizeCall($request->user(), $call);

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
