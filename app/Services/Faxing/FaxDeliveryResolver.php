<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\PendingFax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Settles a fax the provider has finished with: moves its spool files, alerts on failure,
 * and records the outcome. Shared by the delivery webhooks and the fallback poller.
 *
 * A failure is recorded in two steps. The row becomes `failing` here and only becomes
 * `failed` once MoveFailedFaxFiles has taken the .fs out of tosend/. The spool scan's
 * duplicate check deliberately ignores `failed` rows, so that a fax put back with Send
 * Again can go out again. Marking the row `failed` before the move had run left a gap:
 * the scan found the .fs still in tosend/ with no live row and submitted it a second
 * time. Depending on which job ran first, that either sent the recipient the fax again
 * or failed with "Fax payload missing", leaving a second, misleading failure on the
 * failures page.
 */
class FaxDeliveryResolver
{
    public const FAILING = 'failing';

    public function resolve(PendingFax $fax, string $outcome, ?string $reason = null): void
    {
        $details = [
            'jobID' => $fax->job_id,
            'capfile' => $fax->cap_file,
            'filename' => $fax->filename,
            'phone' => $fax->phone,
            'status' => $fax->original_status,
            'fsFileName' => $fax->fs_file_name,
            'account' => $fax->accountLabel() ?? 'Unknown',
            // Neither a provider callback nor the poller knows which Intelligent Series
            // server produced this fax; the pending row is the only thing that does.
            'source_key' => $fax->spool_source_key,
        ];

        if ($outcome === 'success') {
            MoveSuccessfulFaxFiles::dispatch($details, $fax->fax_provider, $fax->spool_source_key);

            $fax->update([
                'delivery_status' => 'success',
                'resolved_at' => Carbon::now(),
            ]);

            return;
        }

        $reason ??= 'Fax delivery failed';

        // Written before the move is dispatched, so that a move run on the sync queue (or
        // by a fast worker) finds the row already `failing` and can finish it.
        $fax->update([
            'delivery_status' => self::FAILING,
            'failure_stage' => FaxFailureLog::STAGE_DELIVERY,
            'failure_reason' => Str::limit($reason, 2000),
            'resolved_at' => Carbon::now(),
        ]);

        MoveFailedFaxFiles::dispatch($details, $fax->fax_provider, $fax->spool_source_key, $fax->id);
        FaxFailAlert::queueSafely($details, $reason);
    }

    /**
     * Re-dispatch the move for failures whose files have not been moved yet.
     *
     * A move can be dropped at dispatch, when a lock left over from an interrupted worker
     * is still held, or its worker can die mid-move. Either way the row would stay
     * `failing` and never reach the failures page. The move's unique lock makes this
     * harmless while the original is still queued.
     *
     * Delivery failures only. A submission failure is also `failing` while it fails over
     * to the other provider, and moving its files then would pull the fax out from under
     * a send still in progress.
     */
    public function retryStalledMoves(int $olderThanSeconds = 300): int
    {
        $stalled = PendingFax::query()
            ->where('delivery_status', self::FAILING)
            ->where('failure_stage', FaxFailureLog::STAGE_DELIVERY)
            ->where('resolved_at', '<', Carbon::now()->subSeconds($olderThanSeconds))
            ->limit(50)
            ->get();

        foreach ($stalled as $fax) {
            MoveFailedFaxFiles::dispatch([
                'jobID' => $fax->job_id,
                'capfile' => $fax->cap_file,
                'filename' => $fax->filename,
                'phone' => $fax->phone,
                'status' => $fax->original_status,
                'fsFileName' => $fax->fs_file_name,
                'source_key' => $fax->spool_source_key,
            ], $fax->fax_provider, $fax->spool_source_key, $fax->id);
        }

        return $stalled->count();
    }

    /**
     * RingCentral's reason for a failed fax, e.g. "RingCentral status: SendingFailed
     * (CallFailed)". The per-recipient faxErrorCode is the only part that says why;
     * messageStatus alone only says that it failed.
     *
     * @param  iterable<mixed>|null  $recipients  the message's `to` list, as arrays or objects
     */
    public static function ringCentralReason(string $label, string $messageStatus, ?iterable $recipients): string
    {
        $codes = [];

        foreach ($recipients ?? [] as $recipient) {
            $code = is_array($recipient) ? ($recipient['faxErrorCode'] ?? null) : ($recipient->faxErrorCode ?? null);

            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));

        return "{$label}: {$messageStatus}".($codes === [] ? '' : ' ('.implode(', ', $codes).')');
    }
}
