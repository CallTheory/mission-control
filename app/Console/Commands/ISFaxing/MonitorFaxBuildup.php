<?php

namespace App\Console\Commands\ISFaxing;

use App\Mail\FaxBuildupAlert;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxSpool;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as CommandStatus;

class MonitorFaxBuildup extends Command
{
    /**
     * Files older than this in a spool folder mean something has stopped processing them.
     */
    private const STALE_MINUTES = 15;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isfax:monitor {fax_provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the fax folders to determine if faxes are not being processed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::FAILURE;
        }

        $provider = $this->argument('fax_provider');

        if (! in_array($provider, FaxSpool::PROVIDERS, true)) {
            $this->error("Unknown fax provider [{$provider}].");

            return CommandStatus::FAILURE;
        }

        $spool = new FaxSpool;
        $paths = [];
        $stuckFiles = [];

        foreach (['tosend', 'fail', 'sent'] as $folder) {
            $stale = $this->staleFiles($spool, $provider, $folder);

            if ($stale === []) {
                continue;
            }

            $paths[] = $spool->path($provider, $folder);

            foreach ($stale as $file) {
                $this->info("[{$folder}] [{$file['name']}] {$file['modified_at']} account=".($file['account'] ?? 'unknown'));

                $stuckFiles[] = $file + ['folder' => FaxSpool::folderLabel($folder)];
            }
        }

        $stalePendingCount = PendingFax::pending()
            ->where('created_at', '<', Carbon::now()->subMinutes(30))
            ->count();

        if ($stalePendingCount > 0) {
            $this->info("Found {$stalePendingCount} pending faxes older than 30 minutes");
            $paths[] = "pending_faxes table: {$stalePendingCount} records older than 30 minutes";
        }

        if ($paths !== []) {
            $this->info('Found unexpected fax files older than '.self::STALE_MINUTES.' minutes');
            Mail::queue(new FaxBuildupAlert($paths, $stuckFiles, $provider));
        }

        return CommandStatus::SUCCESS;
    }

    /**
     * Fax files that have been sitting in a folder past the stale threshold.
     *
     * The descriptors carry the Intelligent Series account, so the alert can name whose
     * faxing is stalled instead of only which directory is backing up — which is the
     * first thing anyone wants to know when a phantom file appears.
     *
     * @return array<int, array<string, mixed>>
     */
    private function staleFiles(FaxSpool $spool, string $provider, string $folder): array
    {
        $cutoff = Carbon::now()->subMinutes(self::STALE_MINUTES);

        return array_values(array_filter(
            $spool->files($provider, $folder),
            fn (array $file) => in_array($file['type'], ['cap', 'fs'], true)
                && Carbon::parse($file['modified_at'])->lessThan($cutoff)
        ));
    }
}
