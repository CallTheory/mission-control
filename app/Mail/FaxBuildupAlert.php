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

    /**
     * Which spool source is backing up.
     *
     * Two Intelligent Series servers otherwise produce byte-identical "Fax Buildup
     * Warning" emails and nobody can tell which one is stuck — the exact question this
     * alert exists to answer.
     *
     * Defaulted because this is a queued mailable: a message serialized before sources
     * existed is unserialized against this class, and a typed property with no default
     * would be left uninitialized and fatal when the view rendered it.
     */
    public string $sourceKey = 'mfax';

    public string $sourceName = 'Default';

    private DataSource $datasource;

    /**
     * Create a new message instance.
     *
     * @param  array<int, string>  $paths
     * @param  array<int, array<string, mixed>>  $stuckFiles
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        array $paths,
        array $stuckFiles = [],
        string $provider = 'mfax',
        string $sourceKey = 'mfax',
        string $sourceName = 'Default',
    ) {
        $this->datasource = DataSource::firstOrFail();

        if (empty($this->datasource->fax_buildup_notification_email)) {
            throw new InvalidArgumentException('Fax buildup notification email is not configured.');
        }

        $this->paths = $paths;
        $this->stuckFiles = $stuckFiles;
        $this->sourceKey = $sourceKey;
        $this->sourceName = $sourceName;
        $this->spoolUrl = secure_url('/utilities/cloud-faxing'
            .($provider === 'ringcentral' ? '/ringcentral' : '')
            .'?source='.urlencode($sourceKey));
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
            ->subject("Fax Buildup Warning — {$this->sourceName}")
            ->markdown('emails.faxes.buildup');
        // ->text('emails.faxes.buildup-text');
    }
}
