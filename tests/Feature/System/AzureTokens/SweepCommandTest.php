<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Jobs\SweepAzureCredentials;
use App\Mail\AzureCredentialExpiryAlert;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use App\Models\DataSource;
use App\Models\System\Settings;
use App\Services\Azure\CredentialSweeper;
use App\Services\Azure\ExpiryAlerter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The scheduled entry point, and the job the dashboard's "Sweep now" button uses.
 */
class SweepCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://login.microsoftonline.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function configure(bool $enabled = true): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'secret-1',
            'azure_enabled' => $enabled,
        ]);
    }

    private function fakeTenant(string $endDateTime = '2027-01-01T00:00:00Z'): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/v1.0/applications*' => Http::response([
                'value' => [[
                    'id' => 'object-1',
                    'appId' => 'app-1',
                    'displayName' => 'Billing API',
                    'passwordCredentials' => [[
                        'keyId' => 'key-1',
                        'displayName' => 'Primary',
                        'endDateTime' => $endDateTime,
                    ]],
                ]],
            ]),
            'https://graph.microsoft.com/v1.0/servicePrincipals*' => Http::response(['value' => []]),
        ]);
    }

    public function test_it_sweeps_and_lists_the_soonest_expiries(): void
    {
        $this->configure();
        $this->fakeTenant();

        $this->artisan('azure:sweep-credentials')
            ->expectsOutputToContain('Swept 1 app registrations')
            ->expectsOutputToContain('Billing API')
            ->assertSuccessful();

        $this->assertSame(1, AzureCredential::count());
    }

    public function test_it_refuses_to_run_without_credentials(): void
    {
        $this->artisan('azure:sweep-credentials')
            ->expectsOutputToContain('Entra ID is not configured')
            ->assertFailed();

        $this->assertSame(0, AzureCredentialSweep::count());
    }

    public function test_it_does_not_sweep_while_the_integration_is_switched_off(): void
    {
        $this->configure(enabled: false);
        $this->fakeTenant();

        $this->artisan('azure:sweep-credentials')
            ->expectsOutputToContain('switched off')
            ->assertSuccessful();

        $this->assertSame(0, AzureCredentialSweep::count());
    }

    public function test_force_sweeps_even_when_switched_off(): void
    {
        $this->configure(enabled: false);
        $this->fakeTenant();

        $this->artisan('azure:sweep-credentials --force')->assertSuccessful();

        $this->assertSame(1, AzureCredential::count());
    }

    public function test_it_alerts_on_what_the_sweep_found(): void
    {
        $this->configure();
        Settings::create([
            'azure_tokens_alert_enabled' => true,
            'azure_tokens_alert_recipients' => 'identity@example.com',
        ]);
        $this->fakeTenant(now()->addDays(2)->toIso8601ZuluString());

        $this->artisan('azure:sweep-credentials')
            ->expectsOutputToContain('Alerted on 1 credential')
            ->assertSuccessful();

        Mail::assertQueued(AzureCredentialExpiryAlert::class);
        $this->assertSame(1, AzureCredentialSweep::mostRecent()->alerts_sent);
    }

    public function test_no_alerts_sweeps_without_sending_anything(): void
    {
        $this->configure();
        Settings::create([
            'azure_tokens_alert_enabled' => true,
            'azure_tokens_alert_recipients' => 'identity@example.com',
        ]);
        $this->fakeTenant(now()->addDays(2)->toIso8601ZuluString());

        $this->artisan('azure:sweep-credentials --no-alerts')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull(AzureCredential::sole()->alerted_threshold);
    }

    public function test_a_failed_sweep_reports_the_reason_and_exits_non_zero(): void
    {
        $this->configure();
        Http::fake([
            self::TOKEN_URL => Http::response(['error_description' => 'AADSTS7000222: secret expired'], 401),
        ]);

        $this->artisan('azure:sweep-credentials')
            ->expectsOutputToContain('client secret for the token watcher itself has expired')
            ->assertFailed();

        $this->assertSame(AzureCredentialSweep::STATUS_FAILED, AzureCredentialSweep::mostRecent()->status);
    }

    public function test_the_job_skips_a_disabled_integration(): void
    {
        $this->configure(enabled: false);
        $this->fakeTenant();

        (new SweepAzureCredentials)->handle(
            app(CredentialSweeper::class),
            app(ExpiryAlerter::class),
        );

        $this->assertSame(0, AzureCredentialSweep::count());
    }

    /**
     * A sweep that fails is recorded and logged, but must not bubble out of the job
     * and land in failed_jobs: there is nothing to retry, and the dashboard already
     * shows the reason.
     */
    public function test_the_job_swallows_a_sweep_failure(): void
    {
        $this->configure();
        Http::fake([
            self::TOKEN_URL => Http::response(['error_description' => 'AADSTS7000215: bad secret'], 401),
        ]);

        (new SweepAzureCredentials)->handle(
            app(CredentialSweeper::class),
            app(ExpiryAlerter::class),
        );

        $this->assertSame(AzureCredentialSweep::STATUS_FAILED, AzureCredentialSweep::mostRecent()->status);
    }
}
