<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Enums\AzureCredentialStatus;
use App\Jobs\SweepAzureCredentials;
use App\Livewire\System\AzureTokens\Alerting;
use App\Livewire\System\AzureTokens\Credentials;
use App\Livewire\System\AzureTokens\Overview;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use App\Models\DataSource;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The dashboard itself: the counts, the staleness warning that keeps a dead
 * collector from reading as a healthy tenant, and the acknowledge action.
 */
class DashboardTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
        $this->admin = $this->createUserWithRole($this->team, 'admin');
        $this->actingAs($this->admin);
    }

    private function configureEntra(bool $enabled = true): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'secret-1',
            'azure_enabled' => $enabled,
        ]);
    }

    public function test_the_stat_cards_count_each_band(): void
    {
        AzureCredential::factory()->count(2)->expired()->create();
        AzureCredential::factory()->expiringInDays(5)->create();
        AzureCredential::factory()->expiringInDays(20)->create();
        AzureCredential::factory()->expiringInDays(200)->create();
        AzureCredential::factory()->acknowledged()->expiringInDays(2)->create();
        AzureCredential::factory()->removed()->expiringInDays(1)->create();

        $stats = Livewire::test(Overview::class)->instance()->stats;

        // Six present credentials; the removed one counts towards nothing, because
        // it is gone from Azure.
        $this->assertSame(6, $stats['total']);
        $this->assertSame(3, $stats['expiring']);
        $this->assertSame(2, $stats['expired']);
        $this->assertSame(1, $stats['acknowledged']);
    }

    public function test_it_warns_when_no_sweep_has_completed_recently(): void
    {
        $this->configureEntra();
        AzureCredentialSweep::factory()->stale()->create();

        Livewire::test(Overview::class)
            ->assertSee('Credential data is stale')
            ->assertSee('client secret');
    }

    public function test_a_recent_sweep_raises_no_warning(): void
    {
        $this->configureEntra();
        AzureCredentialSweep::factory()->create();

        Livewire::test(Overview::class)->assertDontSee('Credential data is stale');
    }

    /**
     * A failed sweep is recent, so the staleness check alone would not flag it; the
     * reason it failed is what the administrator needs.
     */
    public function test_the_reason_the_last_sweep_failed_is_shown(): void
    {
        $this->configureEntra();
        AzureCredentialSweep::factory()->failed('The client secret has expired.')->create();

        Livewire::test(Overview::class)
            ->assertSee('The last sweep failed')
            ->assertSee('The client secret has expired.');
    }

    public function test_it_tells_an_administrator_when_entra_is_not_configured(): void
    {
        Livewire::test(Overview::class)->assertSee('Entra ID is not configured');
    }

    public function test_it_tells_an_administrator_when_sweeps_are_switched_off(): void
    {
        $this->configureEntra(enabled: false);

        Livewire::test(Overview::class)->assertSee('Sweeps are switched off');
    }

    public function test_sweep_now_queues_a_sweep(): void
    {
        Queue::fake();
        $this->configureEntra();

        Livewire::test(Overview::class)
            ->call('sweepNow')
            ->assertSet('sweepQueued', true);

        Queue::assertPushed(SweepAzureCredentials::class);
    }

    public function test_sweep_now_does_nothing_without_credentials(): void
    {
        Queue::fake();

        Livewire::test(Overview::class)->call('sweepNow');

        Queue::assertNothingPushed();
    }

    public function test_the_table_hides_credentials_azure_no_longer_returns(): void
    {
        $present = AzureCredential::factory()->create(['app_name' => 'Present App']);
        $removed = AzureCredential::factory()->removed()->create(['app_name' => 'Removed App']);

        Livewire::test(Credentials::class)
            ->assertCanSeeTableRecords([$present])
            ->assertCanNotSeeTableRecords([$removed]);
    }

    public function test_the_status_filter_matches_the_badge_it_names(): void
    {
        $expired = AzureCredential::factory()->expired()->create();
        $critical = AzureCredential::factory()->expiringInDays(10)->create();
        $warning = AzureCredential::factory()->expiringInDays(25)->create();
        $healthy = AzureCredential::factory()->expiringInDays(200)->create();

        $this->assertSame(AzureCredentialStatus::Critical, $critical->status());
        $this->assertSame(AzureCredentialStatus::Warning, $warning->status());

        Livewire::test(Credentials::class)
            ->filterTable('status', AzureCredentialStatus::Critical->value)
            ->assertCanSeeTableRecords([$critical])
            ->assertCanNotSeeTableRecords([$expired, $warning, $healthy]);
    }

    public function test_acknowledging_a_credential_mutes_it_and_records_who_did_it(): void
    {
        $credential = AzureCredential::factory()->expiringInDays(2)->create();

        Livewire::test(Credentials::class)
            ->callTableAction('acknowledge', $credential);

        $credential->refresh();
        $this->assertTrue($credential->acknowledged);
        $this->assertSame($this->admin->id, $credential->acknowledged_by);
        $this->assertNotNull($credential->acknowledged_at);
    }

    /**
     * Un-acknowledging has to clear the alert history too, or a credential muted
     * while it was already inside the warning window stays silent forever.
     */
    public function test_un_acknowledging_clears_the_alert_history(): void
    {
        $credential = AzureCredential::factory()->acknowledged()->expiringInDays(2)->create([
            'alerted_threshold' => 3,
        ]);

        Livewire::test(Credentials::class)
            ->callTableAction('unacknowledge', $credential);

        $credential->refresh();
        $this->assertFalse($credential->acknowledged);
        $this->assertNull($credential->alerted_threshold);
    }

    public function test_alert_settings_are_saved_and_normalised(): void
    {
        Livewire::test(Alerting::class)
            ->set('enabled', true)
            ->set('recipients', "one@example.com,\n two@example.com")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'one@example.com, two@example.com',
            Settings::first()->azure_tokens_alert_recipients
        );
        $this->assertTrue(Settings::first()->azure_tokens_alert_enabled);
    }

    public function test_a_mistyped_recipient_is_reported_rather_than_dropped(): void
    {
        Livewire::test(Alerting::class)
            ->set('enabled', true)
            ->set('recipients', 'one@example.com, two@example')
            ->call('save')
            ->assertHasErrors('recipients');

        $this->assertNull(Settings::first());
    }

    public function test_alerting_cannot_be_enabled_without_a_recipient(): void
    {
        Livewire::test(Alerting::class)
            ->set('enabled', true)
            ->set('recipients', '')
            ->call('save')
            ->assertHasErrors('recipients');
    }
}
