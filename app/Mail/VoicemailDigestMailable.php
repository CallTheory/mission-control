<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoicemailDigestMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public VoicemailDigest $schedule;

    public array $recordings;

    public Carbon $startDate;

    public Carbon $endDate;

    /**
     * Create a new message instance.
     */
    public function __construct(
        VoicemailDigest $schedule,
        array $recordings,
        Carbon $startDate,
        Carbon $endDate
    ) {
        $this->schedule = $schedule;
        $this->recordings = $recordings;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->queue = 'outbound-email';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->schedule->recipients,
            subject: $this->schedule->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.voicemail-digest.html',
            text: 'emails.voicemail-digest.text',
            with: [
                'schedule' => $this->schedule,
                'recordings' => $this->recordings,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'includeTranscription' => $this->schedule->include_transcription,
                'includeCallMetadata' => $this->schedule->include_call_metadata,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->recordings as $recording) {
            if (! empty($recording['mp3_data'])) {
                $attachments[] = Attachment::fromData(
                    fn () => base64_decode($recording['mp3_data']),
                    "recording_{$recording['call_id']}.mp3"
                )->withMime('audio/mpeg');
            }
        }

        return $attachments;
    }
}
