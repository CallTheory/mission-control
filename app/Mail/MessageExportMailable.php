<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MessageExport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MessageExportMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public MessageExport $export;

    public string $csvContent;

    public Carbon $startDate;

    public Carbon $endDate;

    public int $messageCount;

    public function __construct(
        MessageExport $export,
        string $csvContent,
        Carbon $startDate,
        Carbon $endDate,
        int $messageCount,
    ) {
        $this->export = $export;
        $this->csvContent = $csvContent;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->messageCount = $messageCount;
        $this->queue = 'outbound-email';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->export->recipients,
            subject: $this->export->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.message-export',
            with: [
                'export' => $this->export,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'messageCount' => $this->messageCount,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = 'message-export-' . now($this->export->timezone)->format('Y-m-d_His') . '.csv';

        return [
            Attachment::fromData(
                fn () => $this->csvContent,
                $filename,
            )->withMime('text/csv'),
        ];
    }
}
