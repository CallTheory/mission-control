<?php

namespace App\Mail;

use App\Models\InboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForwardInboundEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public InboundEmail $email;

    public string $forward_to;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(InboundEmail $email, string $forward_to)
    {
        $this->email = $email;
        $this->forward_to = $forward_to;
        $this->queue = 'outbound-email';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->to($this->forward_to)
            ->subject($this->email->subject)
            ->view('emails.inbound-forward-html')
            ->text('emails.inbound-forward-text');
    }
}
