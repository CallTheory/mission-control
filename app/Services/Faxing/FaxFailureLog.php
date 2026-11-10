<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\PendingFax;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records a fax that failed before the provider ever saw it.
 *
 * A pending_faxes row is written when the provider returns 200, so until now a failed
 * submission produced only an email. The fax status pages list the *provider's* history,
 * and a fax the provider never received cannot be in it — so the people who got the
 * failure email had nothing to look at and no way to retry.
 *
 * The row this writes is deliberately the same shape as a delivered fax's, minus the
 * provider id it never got, so one list can show everything that failed regardless of
 * where it fell over.
 */
class FaxFailureLog
{
    public const STAGE_SUBMISSION = 'submission';

    public const STAGE_DELIVERY = 'delivery';

    /**
     * Callers that queue MoveFailedFaxFiles afterwards pass FaxDeliveryResolver::FAILING and
     * hand the returned row's id to the move, which marks it `failed` once the .fs has left
     * tosend/. Marking it `failed` straight away let the spool scan, which ignores `failed`
     * rows, submit the same fax again before the move had run. The scan's own
     * payload-missing path moves the file itself and keeps the default.
     *
     * @param  array<string, mixed>  $fax  the parsed .fs details the send job was holding
     */
    public function recordSubmissionFailure(array $fax, string $provider, string $sourceKey, string $reason, string $status = 'failed'): ?PendingFax
    {
        try {
            // The send job retries, and each attempt that exhausts its tries lands here,
            // as does the other provider's attempt after a failover. Update rather than
            // insert so one fax is one row however many attempts it took.
            $row = PendingFax::query()
                ->where('fs_file_name', (string) ($fax['fsFileName'] ?? ''))
                ->where('spool_source_key', $sourceKey)
                ->whereIn('delivery_status', ['failed', FaxDeliveryResolver::FAILING])
                ->where('failure_stage', self::STAGE_SUBMISSION)
                ->whereNull('retried_at')
                ->latest('id')
                ->first() ?? new PendingFax([
                    'fs_file_name' => (string) ($fax['fsFileName'] ?? ''),
                    'spool_source_key' => $sourceKey,
                    'failure_stage' => self::STAGE_SUBMISSION,
                ]);

            $row->fill([
                'delivery_status' => $status,
                'api_fax_id' => null,
                'fax_provider' => $provider,
                'job_id' => (int) ($fax['jobID'] ?? 0),
                'cap_file' => (string) ($fax['capfile'] ?? ''),
                'filename' => (string) ($fax['filename'] ?? ''),
                'phone' => (string) ($fax['phone'] ?? ''),
                'original_status' => (string) ($fax['status'] ?? ''),
                'routing_reason' => $fax['routing_reason'] ?? null,
                'failure_reason' => Str::limit($reason, 2000),
                'resolved_at' => now(),
            ])->save();

            return $row;
        } catch (Throwable $e) {
            // Never let bookkeeping turn a failed fax into a failed *handler*: the email
            // and the fail/ move still have to happen.
            Log::error('Unable to record fax submission failure: '.$e->getMessage(), [
                'fs_file_name' => $fax['fsFileName'] ?? null,
                'provider' => $provider,
                'source' => $sourceKey,
            ]);

            return null;
        }
    }
}
