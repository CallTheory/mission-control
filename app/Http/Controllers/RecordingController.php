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
        $allowedAccounts = $request->user()->currentTeam->allowed_accounts ?? '';
        $allowedBilling = $request->user()->currentTeam->allowed_billing ?? '';

        // Fail closed for "unrestricted" teams (no allow-lists configured): they would
        // otherwise be able to fetch any call's recording by enumerating call ids.
        // Checked before the (switch-DB) Call lookup so denied requests fail fast.
        if (strlen(trim($allowedAccounts)) === 0 && strlen(trim($allowedBilling)) === 0
            && ! config('recordings.allow_unrestricted_teams')) {
            abort(403);
        }

        try {
            $call = new Call(['ISCallId' => $isCallID]);
        } catch (Exception $e) {
            abort(400);
        }

        if (Helpers::allowedAccountAccess(
            $call->ClientNumber,
            $call->BillingCode ?? '',
            $allowedAccounts,
            $allowedBilling
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
