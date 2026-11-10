<?php

namespace App\Mail;

use App\Models\DataSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class FaxFailAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $fax;

    private DataSource $datasource;

    public string $details;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $fax, ?string $details = null)
    {
        $this->details = $details;
        $this->fax = $fax;
        $this->datasource = DataSource::firstOrFail();
        $this->queue = 'outbound-email';

        if (empty($this->datasource->fax_failure_notification_email)) {
            throw new InvalidArgumentException('Fax buildup notification email is not configured.');
        }
    }

    /**
     * Queue the alert, never letting a mail problem interrupt failure handling.
     *
     * The constructor throws when no notification address is configured. Every caller
     * sits in the middle of failing a fax, and a throw there skipped what came after it:
     * in the send jobs the alert was queued before MoveFailedFaxFiles, so the .fs stayed
     * in tosend/ and was submitted again on the next scan; in the webhook and poller it
     * left the pending row unresolved. The alert is the least important step, so it is
     * the one that gives way.
     *
     * @param  array<string, mixed>  $fax
     */
    public static function queueSafely(array $fax, ?string $details = null): void
    {
        try {
            Mail::queue(new self($fax, $details));
        } catch (Throwable $e) {
            Log::warning('Fax failure alert not sent: '.$e->getMessage(), [
                'fsFileName' => $fax['fsFileName'] ?? null,
                'jobID' => $fax['jobID'] ?? null,
            ]);
        }
    }

    /**
     * Which spool source the failing fax came from, when the dispatching job recorded it.
     */
    private function sourceLabel(): ?string
    {
        $key = $this->fax['source_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->to($this->datasource->fax_failure_notification_email)
            // Name the spool source: with several Intelligent Series servers an
            // unqualified subject leaves the reader unable to tell which one failed.
            ->subject('Fax Failure Notification'.($this->sourceLabel() === null ? '' : " — {$this->sourceLabel()}"))
            ->markdown('emails.faxes.failure')
            ->text('emails.faxes.failure-text');
    }
}
