<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Enums\AzureCredentialSource;
use App\Enums\AzureCredentialType;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use App\Services\Azure\CredentialSweeper;
use App\Services\Azure\GraphClient;
use App\Services\Azure\GraphCredentials;
use App\Services\Azure\GraphException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The collector. Everything here is about the reconciliation, because a sweep that
 * silently misses a page or wrongly marks a live credential removed is a dashboard
 * that lies in the direction of "nothing is wrong".
 */
class CredentialSweeperTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://login.microsoftonline.com/*';

    private const APPS_URL = 'https://graph.microsoft.com/v1.0/applications*';

    private const SPS_URL = 'https://graph.microsoft.com/v1.0/servicePrincipals*';

    private function sweeper(): CredentialSweeper
    {
        return new CredentialSweeper(new GraphClient(new GraphCredentials(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: 'secret-1',
            enabled: true,
        )));
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private static function app(string $name, array $entries = [], array $certificates = []): array
    {
        return [
            'id' => 'object-'.$name,
            'appId' => 'app-'.$name,
            'displayName' => $name,
            'passwordCredentials' => $entries,
            'keyCredentials' => $certificates,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function secret(string $keyId, string $endDateTime, ?string $displayName = null, ?string $hint = null): array
    {
        return [
            'keyId' => $keyId,
            'displayName' => $displayName,
            'hint' => $hint,
            'startDateTime' => '2026-01-01T00:00:00Z',
            'endDateTime' => $endDateTime,
        ];
    }

    public function test_it_flattens_applications_and_service_principals_into_rows(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [
                    self::app('Billing', [self::secret('key-1', '2027-01-01T00:00:00Z', 'Primary', 'abc')]),
                ],
            ]),
            self::SPS_URL => Http::response([
                'value' => [
                    self::app('SamlApp', [], [[
                        'keyId' => 'cert-1',
                        'displayName' => 'Signing',
                        'customKeyIdentifier' => base64_encode(hex2bin('ab12')),
                        'endDateTime' => '2026-12-01T00:00:00Z',
                        'usage' => 'Sign',
                    ]]),
                ],
            ]),
        ]);

        $sweep = $this->sweeper()->sweep();

        $this->assertSame(AzureCredentialSweep::STATUS_SUCCESS, $sweep->status);
        $this->assertSame(1, $sweep->applications);
        $this->assertSame(1, $sweep->service_principals);
        $this->assertSame(2, $sweep->credentials_seen);
        $this->assertSame(2, $sweep->credentials_added);

        $secret = AzureCredential::where('key_id', 'key-1')->sole();
        $this->assertSame(AzureCredentialType::Secret, $secret->cred_type);
        $this->assertSame(AzureCredentialSource::Application, $secret->source);
        $this->assertSame('Primary', $secret->label());

        $certificate = AzureCredential::where('key_id', 'cert-1')->sole();
        $this->assertSame(AzureCredentialType::Certificate, $certificate->cred_type);
        $this->assertSame(AzureCredentialSource::ServicePrincipal, $certificate->source);
        // The base64 thumbprint Graph sends is stored as the hex everything else shows.
        $this->assertSame('AB12', $certificate->hint);
    }

    public function test_it_follows_the_next_link_rather_than_stopping_at_the_first_page(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'https://graph.microsoft.com/v1.0/applications?$select=*' => Http::response([
                'value' => [self::app('PageOne', [self::secret('key-1', '2027-01-01T00:00:00Z')])],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/applications?$skiptoken=next',
            ]),
            'https://graph.microsoft.com/v1.0/applications?$skiptoken=next' => Http::response([
                'value' => [self::app('PageTwo', [self::secret('key-2', '2027-02-01T00:00:00Z')])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $sweep = $this->sweeper()->sweep();

        $this->assertSame(2, $sweep->applications);
        $this->assertSame(2, AzureCredential::count());
    }

    /**
     * One certificate is normally listed twice, under usage Sign and usage Verify,
     * with the same keyId. That is one credential, not two.
     */
    public function test_it_collapses_a_certificate_listed_under_both_usages(): void
    {
        $entry = [
            'keyId' => 'cert-1',
            'displayName' => 'Signing',
            'endDateTime' => '2026-12-01T00:00:00Z',
        ];

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Saml', [], [
                    $entry + ['usage' => 'Verify'],
                    $entry + ['usage' => 'Sign'],
                ])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $sweep = $this->sweeper()->sweep();

        $this->assertSame(1, $sweep->credentials_seen);
        $this->assertSame(1, AzureCredential::count());
    }

    public function test_a_credential_azure_no_longer_returns_is_marked_removed_not_deleted(): void
    {
        $gone = AzureCredential::factory()->create([
            'app_object_id' => 'object-Billing',
            'key_id' => 'old-key',
            'last_seen_utc' => Carbon::now()->subDays(3),
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [self::secret('key-1', '2027-01-01T00:00:00Z')])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $sweep = $this->sweeper()->sweep();

        $this->assertSame(1, $sweep->credentials_removed);
        $this->assertNotNull($gone->fresh()->removed_at);
        $this->assertSame(1, AzureCredential::query()->present()->count());
    }

    public function test_a_credential_that_reappears_is_present_again(): void
    {
        AzureCredential::factory()->removed()->create([
            'app_object_id' => 'object-Billing',
            'key_id' => 'key-1',
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [self::secret('key-1', '2027-01-01T00:00:00Z')])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $this->sweeper()->sweep();

        $this->assertNull(AzureCredential::where('key_id', 'key-1')->sole()->removed_at);
    }

    public function test_an_expiry_that_moves_clears_the_alert_history(): void
    {
        AzureCredential::factory()->create([
            'app_object_id' => 'object-Billing',
            'key_id' => 'key-1',
            'end_utc' => Carbon::parse('2026-10-01T00:00:00Z'),
            'alerted_threshold' => 3,
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [self::secret('key-1', '2028-10-01T00:00:00Z')])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $this->sweeper()->sweep();

        $this->assertNull(AzureCredential::where('key_id', 'key-1')->sole()->alerted_threshold);
    }

    public function test_an_unchanged_expiry_keeps_the_alert_history(): void
    {
        AzureCredential::factory()->create([
            'app_object_id' => 'object-Billing',
            'key_id' => 'key-1',
            'end_utc' => Carbon::parse('2026-10-01T00:00:00Z'),
            'alerted_threshold' => 14,
        ]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [self::secret('key-1', '2026-10-01T00:00:00Z')])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $this->sweeper()->sweep();

        $this->assertSame(14, AzureCredential::where('key_id', 'key-1')->sole()->alerted_threshold);
    }

    /**
     * A tenant always contains the watcher's own registration, so an empty read is a
     * broken read -- and acting on it would mark the whole table removed at once.
     */
    public function test_an_empty_application_list_fails_the_sweep_instead_of_removing_everything(): void
    {
        $existing = AzureCredential::factory()->create();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response(['value' => []]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $this->expectException(GraphException::class);

        try {
            $this->sweeper()->sweep();
        } finally {
            $this->assertNull($existing->fresh()->removed_at);
            $this->assertSame(AzureCredentialSweep::STATUS_FAILED, AzureCredentialSweep::mostRecent()->status);
        }
    }

    public function test_a_graph_failure_is_recorded_on_the_sweep_row(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'error' => ['code' => 'Authorization_RequestDenied', 'message' => 'Insufficient privileges.'],
            ], 403),
        ]);

        try {
            $this->sweeper()->sweep();
            $this->fail('The sweep should have thrown.');
        } catch (GraphException $e) {
            $this->assertStringContainsString('Application.Read.All', $e->getMessage());
        }

        $sweep = AzureCredentialSweep::mostRecent();
        $this->assertSame(AzureCredentialSweep::STATUS_FAILED, $sweep->status);
        $this->assertStringContainsString('Insufficient privileges', $sweep->error);
        $this->assertTrue(AzureCredentialSweep::isStale());
    }

    public function test_entries_without_a_key_id_or_expiry_are_skipped(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [
                    ['keyId' => '', 'endDateTime' => '2027-01-01T00:00:00Z'],
                    ['keyId' => 'key-2', 'endDateTime' => null],
                    self::secret('key-3', '2027-01-01T00:00:00Z'),
                ])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $sweep = $this->sweeper()->sweep();

        $this->assertSame(1, $sweep->credentials_seen);
        $this->assertSame(['key-3'], AzureCredential::pluck('key_id')->all());
    }

    public function test_an_unnamed_credential_falls_back_to_the_hint_then_the_key_id(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response([
                'value' => [self::app('Billing', [
                    self::secret('key-hinted', '2027-01-01T00:00:00Z', null, 'k3y'),
                    self::secret('key-abcdef', '2027-01-01T00:00:00Z'),
                ])],
            ]),
            self::SPS_URL => Http::response(['value' => []]),
        ]);

        $this->sweeper()->sweep();

        $this->assertSame('k3y...', AzureCredential::where('key_id', 'key-hinted')->sole()->label());
        $this->assertSame('Key ...abcdef', AzureCredential::where('key_id', 'key-abcdef')->sole()->label());
    }

    /**
     * The same certificate on an application and on its service principal shares a
     * keyId. Both rows are real and both must survive.
     */
    public function test_the_same_key_id_on_two_objects_is_two_rows(): void
    {
        $entry = [[
            'keyId' => 'shared-cert',
            'displayName' => 'SAML signing',
            'endDateTime' => '2026-12-01T00:00:00Z',
        ]];

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            self::APPS_URL => Http::response(['value' => [self::app('Saml', [], $entry)]]),
            self::SPS_URL => Http::response(['value' => [
                ['id' => 'sp-object', 'appId' => 'app-Saml', 'displayName' => 'Saml', 'keyCredentials' => $entry],
            ]]),
        ]);

        $this->sweeper()->sweep();

        $this->assertSame(2, AzureCredential::where('key_id', 'shared-cert')->count());
    }
}
