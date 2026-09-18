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

    public function test_an_unknown_provider_still_fails(): void
    {
        $this->enableSystemFeature('cloud-faxing');

        $this->artisan('isfax:monitor', ['fax_provider' => 'not-a-provider'])
            ->assertExitCode(CommandStatus::FAILURE);
    }
}
