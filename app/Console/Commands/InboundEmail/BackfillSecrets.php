<?php

declare(strict_types=1);

namespace App\Console\Commands\InboundEmail;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Recovers the pre-security-review inbound email secrets for an existing install.
 *
 * The SendGrid parse webhook and the agent forward endpoint used to derive their
 * shared secret from APP_URL -- md5 for the parse URL, sha256 for the forward
 * api_key -- so neither was ever written down anywhere. They now read
 * INBOUND_EMAIL_PARSE_SECRET / INBOUND_EMAIL_FORWARD_SECRET and fail closed when
 * those are unset, which silently breaks an upgraded install: SendGrid is still
 * posting to the old URL and the scripts are still sending the old key.
 *
 * This prints (or writes) exactly those legacy values so the upgrade is a no-op
 * for the outside world. They are guessable by anyone who knows the URL, which is
 * why they were replaced -- so this is a bridge, not a destination. Rotate to
 * random values once the new Destination URL has been set in SendGrid.
 */
class BackfillSecrets extends Command
{
    protected $signature = 'inbound-email:backfill-secrets
                            {--write : Append the values to .env instead of only printing them}';

    protected $description = 'Recover the legacy inbound email shared secrets derived from APP_URL';

    /** Env keys this command manages, mapped to how the old code derived them. */
    private const SECRETS = [
        'INBOUND_EMAIL_PARSE_SECRET' => 'md5',
        'INBOUND_EMAIL_FORWARD_SECRET' => 'sha256',
    ];

    public function handle(): int
    {
        $appUrl = (string) config('app.url');

        if ($appUrl === '') {
            $this->error('APP_URL is not set, so the legacy values cannot be derived.');

            return CommandStatus::FAILURE;
        }

        $values = [];
        foreach (self::SECRETS as $key => $algorithm) {
            $values[$key] = hash($algorithm, $appUrl);
        }

        $this->line('');
        $this->info('Legacy values derived from APP_URL ('.$appUrl.')');
        $this->line('');

        foreach ($values as $key => $value) {
            $this->line(sprintf('  %s=%s  (%s)', $key, $value, self::SECRETS[$key]));
        }

        $this->line('');
        $this->line('These are what SendGrid and your agent scripts are already sending.');

        if (! $this->option('write')) {
            $this->line('Add --write to append them to .env, or copy them across by hand.');
            $this->rotationNotice();

            return CommandStatus::SUCCESS;
        }

        return $this->writeToEnv($values);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeToEnv(array $values): int
    {
        $path = base_path('.env');

        if (! is_writable($path)) {
            $this->error('.env is not writable at '.$path);

            return CommandStatus::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $appended = [];

        foreach ($values as $key => $value) {
            // Never overwrite a value someone has already set -- a rotated secret
            // must survive a second run of this command.
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $contents) === 1) {
                $this->line("  {$key} is already present in .env; leaving it alone.");

                continue;
            }

            $appended[] = "{$key}={$value}";
        }

        if ($appended === []) {
            $this->info('Nothing to write; both keys are already in .env.');

            return CommandStatus::SUCCESS;
        }

        $block = rtrim($contents, "\n")."\n\n# Recovered by inbound-email:backfill-secrets. Rotate when convenient.\n"
            .implode("\n", $appended)."\n";

        file_put_contents($path, $block);

        $this->info('Wrote '.count($appended).' value(s) to .env. Run `php artisan config:clear`.');
        $this->rotationNotice();

        return CommandStatus::SUCCESS;
    }

    private function rotationNotice(): void
    {
        $this->line('');
        $this->comment('Rotate when convenient: set fresh random values, then update the Destination URL');
        $this->comment('at https://app.sendgrid.com/settings/parse and the api_key your agent scripts send.');
        $this->line('');
    }
}
