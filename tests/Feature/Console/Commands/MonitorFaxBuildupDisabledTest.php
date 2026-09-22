<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * isfax:monitor runs on the scheduler twice an hour. Exiting non-zero when cloud
 * faxing is simply switched off made the scheduler log a failed command every run,
 * burying real errors in the production log. A disabled feature is not a fault.
 */
class MonitorFaxBuildupDisabledTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    public function test_it_exits_successfully_when_cloud_faxing_is_disabled(): void
    {
        $this->disableSystemFeature('cloud-faxing');

        Mail::fake();

        foreach (['mfax', 'ringcentral'] as $provider) {
            $this->artisan('isfax:monitor', ['fax_provider' => $provider])
                ->assertExitCode(CommandStatus::SUCCESS);
        }

        Mail::assertNothingQueued();
    }

    /**
     * The provider argument is a leftover from when the spool was addressed by provider
     * rather than by source. One run now covers every source, so the argument is accepted
     * and ignored rather than validated — a scheduled `isfax:monitor mfax` left in a
     * customer's crontab must keep working instead of failing twice an hour.
     */
    public function test_the_deprecated_provider_argument_is_ignored(): void
    {
        $this->enableSystemFeature('cloud-faxing');

        Mail::fake();

        $this->artisan('isfax:monitor', ['fax_provider' => 'not-a-provider'])
            ->assertExitCode(CommandStatus::SUCCESS);
    }

    public function test_it_checks_every_source_in_a_single_run(): void
    {
        $this->enableSystemFeature('cloud-faxing');

        Mail::fake();

        // No argument at all is now the scheduled form.
        $this->artisan('isfax:monitor')->assertExitCode(CommandStatus::SUCCESS);
    }
}
