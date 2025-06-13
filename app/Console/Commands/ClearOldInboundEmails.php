<?php

namespace App\Console\Commands;

use App\Models\InboundEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ClearOldInboundEmails extends Command
{
    public int $daysToKeep;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-old-inbound-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes inbond emails and their attachment folders from storage';

    public function __construct()
    {

        $this->daysToKeep = config('utilities.inbound-email.days_to_keep');

        parent::__construct();

    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Prune old inbound emails
        $this->comment('Removing inbound emails older than '.$this->daysToKeep.' days');
        $removeOldInboundEmails = InboundEmail::where('created_at', '<=', now()->subDays($this->daysToKeep))->get();
        foreach ($removeOldInboundEmails as $inboundEmail) {
            $inboundEmail->delete();
        }

        // clear out all attachment folders that aren't in use anymore
        $allInboundEmailIds = Arr::flatten(InboundEmail::all(['id'])->toArray());
        $allInboundEmailAttachmentFolder = Storage::allDirectories('inbound-email');

        foreach ($allInboundEmailAttachmentFolder as $attachmentPath) {
            $inbound_email_id = str_replace('inbound-email/', '', $attachmentPath);
            if (! in_array($inbound_email_id, $allInboundEmailIds)) {
                Storage::deleteDirectory('inbound-email/'.$inbound_email_id);
            }
        }

        return CommandStatus::SUCCESS;

    }
}
