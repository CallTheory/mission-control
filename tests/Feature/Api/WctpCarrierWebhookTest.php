<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SmsProvider;
use App\Http\Controllers\Api\WctpController;
use App\Http\Middleware\ValidateSmsProviderWebhook;
use App\Jobs\ForwardToEnterpriseHost;
use App\Models\DataSource;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Inbound messages and delivery receipts arriving from Bandwidth and Commio.
 */
class WctpCarrierWebhookTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');

        // The WCTP routes are registered only when the flag is on at boot, which it
        // is not in a test, so register them here.
        if (! Route::has('wctp.sms.provider.incoming')) {
            Route::middleware(ValidateSmsProviderWebhook::class)->group(function () {
                Route::post('/wctp/sms/{provider}/incoming', [WctpController::class, 'handleProviderWebhook'])
                    ->name('wctp.sms.provider.incoming');

                Route::post('/wctp/{provider}/callback/{messageId?}', [WctpController::class, 'handleProviderWebhook'])
                    ->name('wctp.provider.callback');
            });

            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function host(array $attributes = []): EnterpriseHost
    {
        return EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'enabled' => true,
            'callback_url' => 'https://example.com/wctp',
            'phone_numbers' => ['+15559998888'],
            ...$attributes,
        ]);
    }

    public function test_bandwidth_inbound_message_is_routed_to_its_host(): void
    {
        Queue::fake();

        DataSource::create(['bandwidth_callback_token' => 'secret-token']);
        $host = $this->host();

        $response = $this->postJson('/wctp/sms/bandwidth/incoming?token=secret-token', [[
            'type' => 'message-received',
            'to' => '+15559998888',
            'message' => [
                'id' => 'bw-inbound-1',
                'from' => '+15551234567',
                'to' => ['+15559998888'],
                'text' => 'Inbound via Bandwidth',
            ],
        ]]);

        $response->assertOk();

        $message = WctpMessage::where('provider_message_id', 'bw-inbound-1')->firstOrFail();

        $this->assertSame($host->id, $message->enterprise_host_id);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('Inbound via Bandwidth', $message->message);
        $this->assertSame(SmsProvider::Bandwidth->value, $message->provider);
        // twilio_sid stays a Twilio SID.
        $this->assertNull($message->twilio_sid);

        Queue::assertPushed(ForwardToEnterpriseHost::class);
    }

    public function test_bandwidth_inbound_is_rejected_without_callback_credentials(): void
    {
        DataSource::create(['bandwidth_callback_token' => 'secret-token']);
        $this->host();

        $this->postJson('/wctp/sms/bandwidth/incoming', [[
            'type' => 'message-received',
            'to' => '+15559998888',
            'message' => ['id' => 'bw-inbound-2', 'from' => '+15551234567', 'to' => ['+15559998888'], 'text' => 'Nope'],
        ]])->assertForbidden();

        $this->postJson('/wctp/sms/bandwidth/incoming?token=wrong', [[
            'type' => 'message-received',
            'to' => '+15559998888',
            'message' => ['id' => 'bw-inbound-3', 'from' => '+15551234567', 'to' => ['+15559998888'], 'text' => 'Nope'],
        ]])->assertForbidden();

        $this->assertSame(0, WctpMessage::count());
    }

    public function test_an_unknown_carrier_is_not_a_route(): void
    {
        DataSource::create(['bandwidth_callback_token' => 'secret-token']);

        $this->postJson('/wctp/sms/carrierpigeon/incoming?token=secret-token', [])
            ->assertNotFound();
    }

    public function test_bandwidth_delivery_receipt_is_matched_by_its_tag(): void
    {
        DataSource::create(['bandwidth_callback_token' => 'secret-token']);
        $host = $this->host();

        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_tagged',
            'provider' => SmsProvider::Bandwidth->value,
            'provider_message_id' => 'bw-out-1',
            'twilio_sid' => null,
        ]);

        // The callback URL configured in the Bandwidth portal carries no message id;
        // the tag in the payload is what identifies the message.
        $this->postJson('/wctp/bandwidth/callback?token=secret-token', [[
            'type' => 'message-delivered',
            'message' => ['id' => 'bw-out-1', 'tag' => 'wctp_tagged'],
        ]])->assertOk();

        $message->refresh();

        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertSame('delivered', cache()->get('wctp_status_wctp_tagged'));
    }

    public function test_bandwidth_failure_receipt_records_the_error(): void
    {
        DataSource::create(['bandwidth_callback_token' => 'secret-token']);
        $host = $this->host();

        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_failing',
            'provider' => SmsProvider::Bandwidth->value,
            'provider_message_id' => 'bw-out-2',
            'twilio_sid' => null,
        ]);

        $this->postJson('/wctp/bandwidth/callback?token=secret-token', [[
            'type' => 'message-failed',
            'errorCode' => 4405,
            'description' => 'Rejected by carrier',
            'message' => ['id' => 'bw-out-2', 'tag' => 'wctp_failing'],
        ]])->assertOk();

        $message->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('4405', (string) $message->error_message);
    }

    public function test_commio_inbound_message_is_routed_to_its_host(): void
    {
        Queue::fake();

        DataSource::create([
            'commio_callback_username' => 'hook-user',
            'commio_callback_password' => 'hook-pass',
        ]);
        $host = $this->host();

        $response = $this->post(
            'https://hook-user:hook-pass@localhost/wctp/sms/commio/incoming',
            [
                'from' => '5551234567',
                'to' => '5559998888',
                'message' => 'Inbound via Commio',
                'guid' => 'commio-inbound-1',
            ],
            ['Authorization' => 'Basic '.base64_encode('hook-user:hook-pass')],
        );

        $response->assertOk();

        $message = WctpMessage::where('provider_message_id', 'commio-inbound-1')->firstOrFail();

        $this->assertSame($host->id, $message->enterprise_host_id);
        $this->assertSame('Inbound via Commio', $message->message);
        $this->assertSame(SmsProvider::Commio->value, $message->provider);

        Queue::assertPushed(ForwardToEnterpriseHost::class);
    }

    public function test_commio_delivery_receipt_is_matched_by_its_guid(): void
    {
        DataSource::create(['commio_callback_token' => 'secret-token']);
        $host = $this->host();

        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_commio',
            'provider' => SmsProvider::Commio->value,
            'provider_message_id' => 'commio-out-1',
            'twilio_sid' => null,
        ]);

        $this->post('/wctp/commio/callback?token=secret-token', [
            'guid' => 'commio-out-1',
            'status' => 'DELIVRD',
        ])->assertOk();

        $message->refresh();

        $this->assertSame('delivered', $message->status);
        $this->assertSame('delivered', cache()->get('wctp_status_wctp_commio'));
    }

    public function test_an_inbound_message_to_an_unclaimed_number_is_acknowledged_and_dropped(): void
    {
        DataSource::create(['bandwidth_callback_token' => 'secret-token']);
        $this->host(['phone_numbers' => ['+15557776666']]);

        // A 2xx: a retry would not find a host either.
        $this->postJson('/wctp/sms/bandwidth/incoming?token=secret-token', [[
            'type' => 'message-received',
            'to' => '+15559998888',
            'message' => ['id' => 'bw-orphan', 'from' => '+15551234567', 'to' => ['+15559998888'], 'text' => 'Nobody'],
        ]])->assertOk();

        $this->assertSame(0, WctpMessage::count());
    }
}
