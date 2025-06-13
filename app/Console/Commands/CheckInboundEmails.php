<?php

namespace App\Console\Commands;

use App\Jobs\InboundRuleMatch;
use App\Models\InboundEmail;
use App\Models\Stats\Helpers;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

class CheckInboundEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbound-emails:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for inbound emails that have not yet been processed.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (Helpers::isSystemFeatureEnabled('inbound-email')) {
            $emails = InboundEmail::whereNull('processed_at')->whereNull('ignored_at')->get();

            if ($emails->count() > 0) {
                foreach ($emails as $email) {
                    InboundRuleMatch::dispatch($email);
                }
            }
        }

        return CommandStatus::SUCCESS;
    }
}
