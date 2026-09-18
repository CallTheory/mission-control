<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Enums\Capability;
use App\Livewire\System\Integrations\EntraId;
use App\Mail\AzureCredentialExpiryAlert;
use App\Models\AzureCredential;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\Azure\GraphClient;
use App\Services\Azure\GraphCredentials;
use App\Services\Azure\GraphException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The credentials side: the Integrations tile, and the Graph client's handling of
 * the two failures that actually happen -- an expired secret and missing consent.
 */
class EntraIdIntegrationTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private const TOKEN_URL = 'https://login.microsoftonline.com/*';

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
        $this->actingAs($this->createUserWithRole($this->team, 'admin'));
    }

    private function client(): GraphClient
    {
        return new GraphClient(new GraphCredentials(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: 'secret-1',
            enabled: true,
        ));
    }

    public function test_the_tile_stores_credentials_and_encrypts_the_secret(): void
    {
        Livewire::test(EntraId::class)
            ->callAction('configure', [
                'azure_tenant_id' => 'tenant-1',
                'azure_client_id' => 'client-1',
                'azure_client_secret' => 'top-secret',
                'azure_enabled' => true,
            ]);

        $datasource = DataSource::sole();
        $this->assertSame('tenant-1', $datasource->azure_tenant_id);
        // The cast decrypts on read; the stored column must not be the plaintext.
        $this->assertSame('top-secret', $datasource->azure_client_secret);
        $this->assertNotSame('top-secret', $datasource->getRawOriginal('azure_client_secret'));
        $this->assertTrue($datasource->azure_enabled);
    }

    public function test_a_blank_secret_keeps_the_stored_one(): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'original-secret',
        ]);

        Livewire::test(EntraId::class)
            ->callAction('configure', [
                'azure_tenant_id' => 'tenant-2',
                'azure_client_id' => 'client-1',
                'azure_client_secret' => '',
                'azure_enabled' => true,
            ]);

        $this->assertSame('original-secret', DataSource::sole()->azure_client_secret);
        $this->assertSame('tenant-2', DataSource::sole()->azure_tenant_id);
    }

    public function test_the_stored_secret_is_never_sent_to_the_browser(): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'original-secret',
        ]);

        Livewire::test(EntraId::class)
            ->mountAction('configure')
            ->assertDontSee('original-secret');
    }

    public function test_the_tile_is_denied_without_the_integrations_capability(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'admin', Capability::SystemIntegrations));

        Livewire::test(EntraId::class)->assertForbidden();
    }

    public function test_the_connection_test_reports_the_tenant_size(): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'secret-1',
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/v1.0/applications*' => Http::response([
                '@odata.count' => 42,
                'value' => [],
            ]),
        ]);

        Livewire::test(EntraId::class)
            ->call('testConnection')
            ->assertSet('testError', null)
            ->assertSet('testResult', 'Authenticated to Microsoft Graph. The tenant has 42 app registrations.');
    }

    public function test_the_connection_test_explains_a_missing_consent(): void
    {
        DataSource::create([
            'azure_tenant_id' => 'tenant-1',
            'azure_client_id' => 'client-1',
            'azure_client_secret' => 'secret-1',
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/v1.0/applications*' => Http::response([
                'error' => ['code' => 'Authorization_RequestDenied', 'message' => 'Insufficient privileges.'],
            ], 403),
        ]);

        $component = Livewire::test(EntraId::class)->call('testConnection');

        $this->assertNull($component->get('testResult'));
        $this->assertStringContainsString('admin consent', $component->get('testError'));
    }

    public function test_an_unconfigured_tenant_is_refused_before_any_request_is_made(): void
    {
        Http::fake();

        $client = new GraphClient(new GraphCredentials(null, null, null, false));

        $this->expectException(GraphException::class);
        $this->expectExceptionMessage('System -> Integrations');

        try {
            $client->accessToken();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_the_token_is_requested_once_and_then_reused(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/v1.0/applications*' => Http::response(['value' => []]),
        ]);

        $client = $this->client();
        $client->accessToken();
        $client->accessToken();

        Http::assertSentCount(1);
    }

    public function test_the_alert_email_renders(): void
    {
        $credential = AzureCredential::factory()->expiringInDays(2)->create(['app_name' => 'Billing API']);
        $expired = AzureCredential::factory()->expired()->create(['app_name' => 'Legacy Sync']);

        $payload = fn (AzureCredential $c): array => [
            'app_name' => $c->app_name,
            'app_client_id' => $c->app_client_id,
            'source' => $c->source->label(),
            'type' => $c->cred_type->label(),
            'credential' => $c->label(),
            'expires_at' => $c->end_utc->toDayDateTimeString().' UTC',
            'days_remaining' => $c->daysRemaining(),
            'status' => $c->status()->label(),
            'portal_url' => $c->portalUrl(),
        ];

        $mail = new AzureCredentialExpiryAlert(
            [$payload($credential), $payload($expired)],
            ['identity@example.com'],
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Billing API', $rendered);
        $this->assertStringContainsString('Legacy Sync', $rendered);
        $this->assertStringContainsString('already expired', $rendered);
        $this->assertStringContainsString('entra.microsoft.com', $rendered);
        $this->assertStringContainsString('Azure credentials expired', $mail->subjectLine());
    }
}
