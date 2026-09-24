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
     * @param  array<string, mixed>  $fax  the parsed .fs details the send job was holding
     */
    public function recordSubmissionFailure(array $fax, string $provider, string $sourceKey, string $reason): ?PendingFax
    {
        try {
            // The send job retries, and each attempt that exhausts its tries lands here.
            // Update rather than insert so one fax is one row however many attempts it
            // took, and so a retry of an already-recorded failure does not stack up.
            return PendingFax::updateOrCreate(
                [
                    'fs_file_name' => (string) ($fax['fsFileName'] ?? ''),
                    'spool_source_key' => $sourceKey,
                    'delivery_status' => 'failed',
                    'failure_stage' => self::STAGE_SUBMISSION,
                    'retried_at' => null,
                ],
                [
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
                ]
            );
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
