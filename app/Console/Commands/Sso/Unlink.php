<?php

declare(strict_types=1);

namespace App\Console\Commands\Sso;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Clears the stored SAML subject id so accounts fall back to email matching.
 *
 * SAML2\CallbackController::resolveUser() binds sign-in to saml_linked_id, which
 * is what makes an unlink mean something -- but it also means an IdP that gets
 * rebuilt or migrated, and reissues every subject id, locks everyone out of SSO.
 * This is the way back: clear the column and the next assertion re-links by email.
 */
class Unlink extends Command
{
    protected $signature = 'sso:unlink
                            {email? : Clear one account by email address}
                            {--all : Clear every linked account}';

    protected $description = 'Clear stored SAML account links so sign-in falls back to email matching';

    public function handle(): int
    {
        $email = $this->argument('email');
        $all = (bool) $this->option('all');

        if ($all === ($email !== null)) {
            $this->error('Pass either an email address or --all, not both or neither.');

            return CommandStatus::FAILURE;
        }

        if ($all) {
            $count = User::whereNotNull('saml_linked_id')->count();

            if ($count === 0) {
                $this->info('No accounts are linked.');

                return CommandStatus::SUCCESS;
            }

            if (! $this->confirm("Clear the SAML link on {$count} account(s)?", false)) {
                $this->line('Nothing changed.');

                return CommandStatus::SUCCESS;
            }

            User::whereNotNull('saml_linked_id')->update(['saml_linked_id' => null]);
            $this->info("Cleared {$count} account(s). The next assertion re-links each one by email.");

            return CommandStatus::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return CommandStatus::FAILURE;
        }

        if (blank($user->saml_linked_id)) {
            $this->info("{$email} is not linked.");

            return CommandStatus::SUCCESS;
        }

        $user->saml_linked_id = null;
        $user->save();

        $this->info("Cleared the SAML link on {$email}.");

        return CommandStatus::SUCCESS;
    }
}
