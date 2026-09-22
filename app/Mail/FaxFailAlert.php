<?php

namespace App\Mail;

use App\Models\DataSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

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
