<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EncryptWctpMessages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'wctp:encrypt-messages
                            {--dry-run : Show how many messages would be encrypted without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Encrypt existing unencrypted WCTP message content at rest';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $messages = DB::table('wctp_messages')
            ->select(['id', 'message'])
            ->whereNotNull('message')
            ->where('message', '!=', '')
            ->orderBy('id')
            ->get();

        $total = $messages->count();
        $encrypted = 0;
        $skipped = 0;

        if ($this->option('dry-run')) {
            // Check which messages are already encrypted
            foreach ($messages as $msg) {
                if ($this->isAlreadyEncrypted($msg->message)) {
                    $skipped++;
                } else {
                    $encrypted++;
                }
            }

            $this->info("Dry run: {$encrypted} messages would be encrypted, {$skipped} already encrypted, {$total} total.");

            return Command::SUCCESS;
        }

        if ($total === 0) {
            $this->info('No messages to encrypt.');

            return Command::SUCCESS;
        }

        $this->info("Encrypting {$total} messages...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($messages as $msg) {
            if ($this->isAlreadyEncrypted($msg->message)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            DB::table('wctp_messages')
                ->where('id', $msg->id)
                ->update(['message' => encrypt($msg->message)]);

            $encrypted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$encrypted} messages encrypted, {$skipped} already encrypted.");

        return Command::SUCCESS;
    }

    /**
     * Check if a value appears to already be encrypted by Laravel.
     */
    protected function isAlreadyEncrypted(string $value): bool
    {
        // Laravel encrypted values are base64-encoded JSON containing iv, value, mac keys
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
}
