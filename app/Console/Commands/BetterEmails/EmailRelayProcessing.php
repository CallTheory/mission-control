<?php

namespace App\Console\Commands\BetterEmails;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\NoReturn;

class EmailRelayProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email-relay:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handles inbound emails piped from postfix';

    /**
     * Execute the console command.
     */
    #[NoReturn]
    public function handle(): void
    {
        $raw_email = file_get_contents('php://stdin');
        Log::info('Raw Email', [$raw_email]);
        // we need to get these:
        // subject
        // to
        // body
        $mime = mailparse_msg_create();
        mailparse_msg_parse($mime, $raw_email);
        mailparse_msg_free($mime);
        Log::info('Mime Parsed', [$mime]);
        exit;
    }
}
