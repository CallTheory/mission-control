<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BetterEmailsUnsubscribeNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $unsubscribeEmail;

    public string $accountNumber;

    public string $unsubscribeTitle;

    public string $additionalNotes;

    /**
     * Create a new message instance.
     */
    public function __construct(array $config)
    {
        $this->unsubscribeEmail = $config['unsubscribeEmail'];
        $this->accountNumber = $config['accountNumber'];
        $this->unsubscribeTitle = $config['unsubscribeTitle'];
        $this->additionalNotes = $config['additionalNotes'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Better Emails - Unsubscribe Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.better-emails.email-unsubscribe-text',
            markdown: 'emails.better-emails.email-unsubscribe-html',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
