<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * @deprecated Superseded by isfax:scan, which covers every spool source rather than the
 * single provider-named directory this command assumed. Kept for one release because it
 * is documented and likely to appear in a runbook or a hand-rolled cron entry.
 */
class ProcessNewFaxes extends Command
{
    protected $signature = 'isfax:process';

    protected $description = '[Deprecated] Use isfax:scan. Processes new mFax faxes in the TOSEND folder.';

    public function handle(): int
    {
        $this->warn('isfax:process is deprecated; use "isfax:scan --provider=mfax" instead.');

        return $this->call('isfax:scan', ['--provider' => ['mfax']]) === 0
            ? CommandStatus::SUCCESS
            : CommandStatus::FAILURE;
    }
}
