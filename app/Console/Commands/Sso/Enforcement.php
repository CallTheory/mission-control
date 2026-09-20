<?php

declare(strict_types=1);

namespace App\Console\Commands\Sso;

use App\Models\System\Settings;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Break-glass for the authentication policies.
 *
 * When the linked-SSO lock is on and the identity provider goes down, every
 * linked user is refused at the login form -- including the admins who would
 * otherwise switch it off in the UI. This is the way back, and it deliberately
 * requires shell access on the server rather than a credential.
 *
 * See also sso:unlink, which clears the stored links themselves when an IdP has
 * been rebuilt and reissued every subject id.
 */
class Enforcement extends Command
{
    protected $signature = 'sso:enforcement
                            {--enable : Require linked accounts to sign in through the IdP}
                            {--disable : Allow linked accounts to sign in with a password again}';

    protected $description = 'Show or change whether linked accounts are forced through single sign-on';

    public function handle(): int
    {
        $settings = Settings::first();

        if (! $settings) {
            $this->error('No settings row exists yet, so nothing is being enforced.');

            return CommandStatus::FAILURE;
        }

        $enable = (bool) $this->option('enable');
        $disable = (bool) $this->option('disable');

        if ($enable && $disable) {
            $this->error('Pass either --enable or --disable, not both.');

            return CommandStatus::FAILURE;
        }

        if ($enable || $disable) {
            $settings->auth_enforce_linked_sso = $enable;
            $settings->save();

            $this->info($enable
                ? 'Linked accounts must now sign in through single sign-on.'
                : 'Linked accounts may sign in with a password again.');
        }

        $this->status($settings->fresh());

        return CommandStatus::SUCCESS;
    }

    private function status(Settings $settings): void
    {
        $this->line('');
        $this->line('  Force linked accounts through SSO : '.($settings->auth_enforce_linked_sso ? 'on' : 'off'));
        $this->line('  Require SSO or 2FA for everyone   : '.($settings->auth_require_sso_or_2fa ? 'on' : 'off'));
        $this->line('  SAML enabled                      : '.(((int) $settings->saml2_enabled === 1) ? 'yes' : 'no'));
        $this->line('');

        if ($settings->auth_enforce_linked_sso && (int) $settings->saml2_enabled !== 1) {
            $this->comment('SAML is off, so the lock is inactive -- linked accounts can still sign in.');
            $this->line('');
        }
    }
}
