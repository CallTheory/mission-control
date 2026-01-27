<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\PendingFax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FaxWebhookController extends Controller
{
    public function mfax(Request $request): JsonResponse
    {
        $data = $request->all();
        $faxUuid = $data['uuid'] ?? $data['faxId'] ?? null;
        $status = $data['status'] ?? null;

        if (! $faxUuid || ! $status) {
            return response()->json(['message' => 'Missing required fields'], 422);
        }

        $pendingFax = PendingFax::where('api_fax_id', $faxUuid)
            ->where('fax_provider', 'mfax')
            ->where('delivery_status', 'pending')
            ->first();

        if (! $pendingFax) {
            return response()->json(['message' => 'No matching pending fax'], 404);
        }

        if (in_array($status, ['success', 'completed'])) {
            $this->resolveFax($pendingFax, 'success');
        } elseif (in_array($status, ['failed', 'cancelled'])) {
            $this->resolveFax($pendingFax, 'failed', "MFax webhook status: {$status}");
        }

        return response()->json(['message' => 'ok']);
    }

    public function ringcentral(Request $request): JsonResponse
    {
        $data = $request->all();

        // Handle RingCentral validation token handshake
        if ($request->header('Validation-Token')) {
            return response()->json([], 200)
                ->header('Validation-Token', $request->header('Validation-Token'));
        }

        $messageId = $data['body']['id'] ?? null;
        $messageStatus = $data['body']['messageStatus'] ?? null;

        if (! $messageId || ! $messageStatus) {
            return response()->json(['message' => 'Missing required fields'], 422);
        }

        $pendingFax = PendingFax::where('api_fax_id', (string) $messageId)
            ->where('fax_provider', 'ringcentral')
            ->where('delivery_status', 'pending')
            ->first();

        if (! $pendingFax) {
            return response()->json(['message' => 'No matching pending fax'], 404);
        }

        if (in_array($messageStatus, ['Sent', 'Delivered'])) {
            $this->resolveFax($pendingFax, 'success');
        } elseif (in_array($messageStatus, ['DeliveryFailed', 'SendingFailed'])) {
            $this->resolveFax($pendingFax, 'failed', "RingCentral webhook status: {$messageStatus}");
        }

        return response()->json(['message' => 'ok']);
    }

    private function resolveFax(PendingFax $pendingFax, string $outcome, ?string $reason = null): void
    {
        $faxFsDetails = [
            'jobID' => $pendingFax->job_id,
            'capfile' => $pendingFax->cap_file,
            'filename' => $pendingFax->filename,
            'phone' => $pendingFax->phone,
            'status' => $pendingFax->original_status,
            'fsFileName' => $pendingFax->fs_file_name,
        ];

        if ($outcome === 'success') {
            MoveSuccessfulFaxFiles::dispatch($faxFsDetails, $pendingFax->fax_provider);
            Log::info("Webhook: Fax #{$pendingFax->id} (job {$pendingFax->job_id}) delivered successfully.");
        } else {
            MoveFailedFaxFiles::dispatch($faxFsDetails, $pendingFax->fax_provider);
            Mail::queue(new FaxFailAlert($faxFsDetails, $reason ?? 'Fax delivery failed'));
            Log::error("Webhook: Fax #{$pendingFax->id} (job {$pendingFax->job_id}) failed: {$reason}");
        }

        $pendingFax->update([
            'delivery_status' => $outcome,
            'resolved_at' => Carbon::now(),
        ]);
    }
}
