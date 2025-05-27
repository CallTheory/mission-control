<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class PrettyLog extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $subject;

    public array $email_details;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $email_details)
    {
        if(Validator::make(['theme' => $email_details['theme']], [
            'theme' => 'required|in:standard,dark,custom',
        ])->fails()){
            throw new NotFoundResourceException('Theme not found');
        }
        $this->email_details = $email_details;
        $this->subject = $email_details['subject'];
        $this->queue = 'better-emails';

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->view("emails.better-emails.{$this->email_details['theme']}")
            ->text('emails.better-emails.plain_text');
    }
}
