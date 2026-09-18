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

    /**
     * The individual stuck files, each with the Intelligent Series account it belongs to,
     * so the alert says whose faxing is stalled rather than only which folder is backing
     * up. Empty when the buildup is in the pending_faxes table rather than on disk.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $stuckFiles;

    /**
     * Where to send the reader to clear the buildup, now that spool files can be deleted
     * from the fax utility page.
     */
    public string $spoolUrl;

    private DataSource $datasource;

    /**
     * Create a new message instance.
     *
     * @param  array<int, string>  $paths
     * @param  array<int, array<string, mixed>>  $stuckFiles
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $paths, array $stuckFiles = [], string $provider = 'mfax')
    {
        $this->datasource = DataSource::firstOrFail();

        if (empty($this->datasource->fax_buildup_notification_email)) {
            throw new InvalidArgumentException('Fax buildup notification email is not configured.');
        }

        $this->paths = $paths;
        $this->stuckFiles = $stuckFiles;
        $this->spoolUrl = secure_url('/utilities/cloud-faxing'.($provider === 'ringcentral' ? '/ringcentral' : ''));
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
        // ->text('emails.faxes.buildup-text');
    }
}
