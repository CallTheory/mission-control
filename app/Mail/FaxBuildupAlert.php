<?php

namespace App\Mail;

use App\Models\DataSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class FaxBuildupAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $paths;

    private DataSource $datasource;

    /**
     * Create a new message instance.
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public function __construct(array $paths)
    {
        $this->datasource = DataSource::firstOrFail();
        
        if (empty($this->datasource->fax_buildup_notification_email)) {
            throw new InvalidArgumentException('Fax buildup notification email is not configured.');
        }
        
        $this->paths = $paths;
        $this->queue = 'outbound-email';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->to($this->datasource->fax_buildup_notification_email)
            ->subject('Fax Buildup Warning')
            ->markdown('emails.faxes.buildup');
            //->text('emails.faxes.buildup-text');
    }
}
