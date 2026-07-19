<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\API\Webhooks\FaxWebhookController;
use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaxWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register fax webhook routes directly (bypasses cloud-faxing feature flag check at boot time)
        Route::middleware('api')->prefix('api')->group(function () {
            Route::post('/webhooks/fax/mfax', [FaxWebhookController::class, 'mfax'])
                ->name('api.webhooks.fax.mfax');
            Route::post('/webhooks/fax/ringcentral', [FaxWebhookController::class, 'ringcentral'])
                ->name('api.webhooks.fax.ringcentral');
        });

        // Avoid Redis dependency in tests
        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->withoutMiddleware(ThrottleRequests::class);

        // Fax callbacks now require a shared secret; supply it by default so the
        // behavioural tests below exercise the business logic, not the auth gate.
        config(['services.fax.webhook_secret' => 'test-fax-secret']);
        $this->withHeader('X-Webhook-Secret', 'test-fax-secret');

        DataSource::create([
            'fax_failure_notification_email' => 'test@example.com',
        ]);
    }

    public function test_mfax_webhook_rejects_missing_secret(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Webhook-Secret', 'wrong-secret')
            ->postJson('/api/webhooks/fax/mfax', ['uuid' => 'x', 'status' => 'success']);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_ringcentral_webhook_rejects_missing_secret(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Webhook-Secret', 'wrong-secret')
            ->postJson('/api/webhooks/fax/ringcentral', ['body' => ['id' => 'x', 'messageStatus' => 'Sent']]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_fax_webhook_fails_closed_when_secret_unconfigured(): void
    {
        Queue::fake();
        config(['services.fax.webhook_secret' => null]);

        $this->withHeader('X-Webhook-Secret', '')
            ->postJson('/api/webhooks/fax/mfax', ['uuid' => 'x', 'status' => 'success'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    // --- MFax Webhook Tests ---

    public function test_mfax_webhook_resolves_success(): void
    {
        Queue::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'api_fax_id' => 'mfax-uuid-123',
            'fax_provider' => 'mfax',
        ]));

        $response = $this->postJson('/api/webhooks/fax/mfax', [
            'uuid' => 'mfax-uuid-123',
            'status' => 'success',
        ]);

        $response->assertOk();
        $pendingFax->refresh();
        $this->assertEquals('success', $pendingFax->delivery_status);
        $this->assertNotNull($pendingFax->resolved_at);
        Queue::assertPushed(MoveSuccessfulFaxFiles::class);
        Queue::assertNotPushed(MoveFailedFaxFiles::class);
    }

    public function test_mfax_webhook_resolves_failure(): void
    {
        Queue::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'api_fax_id' => 'mfax-uuid-456',
            'fax_provider' => 'mfax',
        ]));

        $response = $this->postJson('/api/webhooks/fax/mfax', [
            'uuid' => 'mfax-uuid-456',
            'status' => 'failed',
        ]);

        $response->assertOk();
        $pendingFax->refresh();
        $this->assertEquals('failed', $pendingFax->delivery_status);
        Queue::assertPushed(MoveFailedFaxFiles::class);
        Mail::assertQueued(FaxFailAlert::class);
    }

    public function test_mfax_webhook_returns_422_when_missing_fields(): void
    {
        $response = $this->postJson('/api/webhooks/fax/mfax', []);
        $response->assertStatus(422);
    }

    public function test_mfax_webhook_returns_404_when_no_matching_pending_fax(): void
    {
        $response = $this->postJson('/api/webhooks/fax/mfax', [
            'uuid' => 'nonexistent-uuid',
            'status' => 'success',
        ]);

        $response->assertStatus(404);
    }

    public function test_mfax_webhook_ignores_already_resolved_fax(): void
    {
        Queue::fake();

        PendingFax::create($this->makePendingFaxAttrs([
            'api_fax_id' => 'mfax-resolved',
            'fax_provider' => 'mfax',
            'delivery_status' => 'success',
            'resolved_at' => now(),
        ]));

        $response = $this->postJson('/api/webhooks/fax/mfax', [
            'uuid' => 'mfax-resolved',
            'status' => 'success',
        ]);

        $response->assertStatus(404);
        Queue::assertNothingPushed();
    }

    // --- RingCentral Webhook Tests ---

    public function test_ringcentral_webhook_resolves_success(): void
    {
        Queue::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'api_fax_id' => '98765',
            'fax_provider' => 'ringcentral',
        ]));

        $response = $this->postJson('/api/webhooks/fax/ringcentral', [
            'body' => [
                'id' => 98765,
                'messageStatus' => 'Delivered',
            ],
        ]);

        $response->assertOk();
        $pendingFax->refresh();
        $this->assertEquals('success', $pendingFax->delivery_status);
        Queue::assertPushed(MoveSuccessfulFaxFiles::class);
    }

    public function test_ringcentral_webhook_resolves_failure(): void
    {
        Queue::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'api_fax_id' => '11111',
            'fax_provider' => 'ringcentral',
        ]));

        $response = $this->postJson('/api/webhooks/fax/ringcentral', [
            'body' => [
                'id' => 11111,
                'messageStatus' => 'SendingFailed',
            ],
        ]);

        $response->assertOk();
        $pendingFax->refresh();
        $this->assertEquals('failed', $pendingFax->delivery_status);
        Queue::assertPushed(MoveFailedFaxFiles::class);
        Mail::assertQueued(FaxFailAlert::class);
    }

    public function test_ringcentral_webhook_handles_validation_token(): void
    {
        $response = $this->postJson('/api/webhooks/fax/ringcentral', [], [
            'Validation-Token' => 'abc123',
        ]);

        $response->assertOk();
        $response->assertHeader('Validation-Token', 'abc123');
    }

    public function test_ringcentral_webhook_returns_422_when_missing_fields(): void
    {
        $response = $this->postJson('/api/webhooks/fax/ringcentral', [
            'body' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_ringcentral_webhook_returns_404_when_no_matching_pending_fax(): void
    {
        $response = $this->postJson('/api/webhooks/fax/ringcentral', [
            'body' => [
                'id' => 99999,
                'messageStatus' => 'Delivered',
            ],
        ]);

        $response->assertStatus(404);
    }

    private function makePendingFaxAttrs(array $overrides = []): array
    {
        return array_merge([
            'api_fax_id' => 'test-uuid-'.uniqid(),
            'fax_provider' => 'mfax',
            'job_id' => rand(1, 99999),
            'fs_file_name' => 'test.fs',
            'cap_file' => 'test.cap',
            'filename' => 'test.cap',
            'phone' => '5551234567',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ], $overrides);
    }
}
