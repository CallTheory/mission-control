<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendToAmtelcoSmtp extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $json;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $json)
    {
        $this->subject = $subject;
        $this->json = $json;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.send-to-amtelco-smtp',
        );
    }
}
