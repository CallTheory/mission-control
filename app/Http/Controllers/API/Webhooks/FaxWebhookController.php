<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Concerns\VerifiesSharedSecret;
use App\Http\Controllers\Controller;
use App\Models\PendingFax;
use App\Services\Faxing\FaxDeliveryResolver;
use App\Services\Faxing\FaxDeliveryWebhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FaxWebhookController extends Controller
{
    use VerifiesSharedSecret;

    /**
     * Verify the shared secret carried on a provider callback. Configure the
     * provider's webhook URL with `?token=<secret>` (or send it as the
     * `X-Webhook-Secret` header). Fails closed when the secret is unset.
     */
    private function webhookSecretValid(Request $request): bool
    {
        $provided = $request->header('X-Webhook-Secret') ?? $request->query('token');

        return $this->sharedSecretMatches($provided, config('services.fax.webhook_secret'));
    }

    public function mfax(Request $request): JsonResponse
    {
        if (! $this->webhookSecretValid($request)) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        FaxDeliveryWebhooks::record('mfax');

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
        if (! $this->webhookSecretValid($request)) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        FaxDeliveryWebhooks::record('ringcentral');

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
            $to = $data['body']['to'] ?? null;

            $this->resolveFax($pendingFax, 'failed', FaxDeliveryResolver::ringCentralReason('RingCentral webhook status', $messageStatus, is_array($to) ? $to : null));
        }

        return response()->json(['message' => 'ok']);
    }

    private function resolveFax(PendingFax $pendingFax, string $outcome, ?string $reason = null): void
    {
        app(FaxDeliveryResolver::class)->resolve($pendingFax, $outcome, $reason);

        $outcome === 'success'
            ? Log::info("Webhook: Fax #{$pendingFax->id} (job {$pendingFax->job_id}) delivered successfully.")
            : Log::error("Webhook: Fax #{$pendingFax->id} (job {$pendingFax->job_id}) failed: {$reason}");
    }
}
