<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SmsProvider;
use App\Http\Controllers\Api\WctpController;
use App\Jobs\ProcessWctpMessage;
use App\Models\DataSource;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use App\Services\Sms\SmsGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Which carrier an outbound WCTP message goes out through: the one that owns the
 * sending number, or the system default when that number has none.
 */
class WctpCarrierRoutingTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');

        if (! Route::has('wctp')) {
            Route::post('/wctp', [WctpController::class, 'handle'])->name('wctp');
            Route::post('/wctp/callback/{messageId}', [WctpController::class, 'twilioCallback'])->name('wctp.callback');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function allCarriersConfigured(array $overrides = []): DataSource
    {
        return DataSource::create([
            'twilio_account_sid' => 'AC123',
            'twilio_auth_token' => 'twilio-token',
            'twilio_from_number' => '+15551110000',

            'bandwidth_account_id' => '5000000',
            'bandwidth_application_id' => 'app-123',
            'bandwidth_from_number' => '+15552220000',
            'bandwidth_api_token' => 'api-token',
            'bandwidth_api_secret' => 'api-secret',

            'commio_account_id' => '4321',
            'commio_username' => 'portal-user',
            'commio_api_token' => 'portal-token',
            'commio_from_number' => '+15553330000',
            ...$overrides,
        ]);
    }

    private function submit(string $senderId = 'testhost', string $messageId = 'msg-1'): TestResponse
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="{$senderId}" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="{$messageId}"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Alphanumeric>Outbound test</wctp-Alphanumeric>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        return $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
    }

    private function host(array $attributes = []): EnterpriseHost
    {
        return EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'enabled' => true,
            'phone_numbers' => ['+15559998888'],
            ...$attributes,
        ]);
    }

    public function test_the_carrier_that_owns_the_sending_number_carries_the_message(): void
    {
        Queue::fake();
        $this->allCarriersConfigured();

        $this->host(['number_providers' => ['15559998888' => 'bandwidth']]);

        $this->submit()->assertOk();

        $this->assertSame(
            SmsProvider::Bandwidth->value,
            WctpMessage::where('wctp_message_id', 'msg-1')->firstOrFail()->provider,
        );
    }

    public function test_a_number_with_no_carrier_uses_the_system_default(): void
    {
        Queue::fake();
        $this->allCarriersConfigured(['sms_default_provider' => SmsProvider::Commio->value]);

        $this->host();

        $this->submit()->assertOk();

        $this->assertSame(
            SmsProvider::Commio->value,
            WctpMessage::where('wctp_message_id', 'msg-1')->firstOrFail()->provider,
        );
    }

    public function test_the_default_carrier_is_twilio_when_none_has_been_chosen(): void
    {
        Queue::fake();
        $this->allCarriersConfigured();

        $this->host();

        $this->submit()->assertOk();

        $this->assertSame(
            SmsProvider::Twilio->value,
            WctpMessage::where('wctp_message_id', 'msg-1')->firstOrFail()->provider,
        );
    }

    public function test_a_submit_is_refused_when_the_numbers_carrier_is_not_configured(): void
    {
        Queue::fake();

        // Twilio only; the number claims to be a Bandwidth DID.
        DataSource::create([
            'twilio_account_sid' => 'AC123',
            'twilio_auth_token' => 'twilio-token',
            'twilio_from_number' => '+15551110000',
        ]);

        $this->host(['number_providers' => ['15559998888' => 'bandwidth']]);

        $response = $this->submit();

        $response->assertStatus(503);
        $this->assertStringContainsString('Service unavailable', $response->content());
        $this->assertSame(0, WctpMessage::count());
        Queue::assertNothingPushed();
    }

    public function test_the_job_sends_through_the_messages_own_carrier(): void
    {
        $this->allCarriersConfigured();

        Http::fake([
            'messaging.bandwidth.com/*' => Http::response(['id' => 'bw-sent-1'], 202),
        ]);

        $host = $this->host(['number_providers' => ['15559998888' => 'bandwidth']]);

        $message = WctpMessage::factory()->queued()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_job_1',
            'from' => '+15559998888',
            'to' => '+15551234567',
            'provider' => SmsProvider::Bandwidth->value,
        ]);

        (new ProcessWctpMessage($message))->handle(app(SmsGatewayManager::class));

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('bw-sent-1', $message->provider_message_id);
        // Not a Twilio SID, so the legacy column stays empty.
        $this->assertNull($message->twilio_sid);
        $this->assertNotNull($message->processed_at);
    }

    public function test_the_job_sends_a_commio_message_to_commio(): void
    {
        $this->allCarriersConfigured();

        Http::fake([
            'api.thinq.com/*' => Http::response(['guid' => 'commio-sent-1'], 200),
        ]);

        $host = $this->host(['number_providers' => ['15559998888' => 'commio']]);

        $message = WctpMessage::factory()->queued()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_job_2',
            'from' => '+15559998888',
            'to' => '+15551234567',
            'provider' => SmsProvider::Commio->value,
        ]);

        (new ProcessWctpMessage($message))->handle(app(SmsGatewayManager::class));

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('commio-sent-1', $message->provider_message_id);
        $this->assertNull($message->twilio_sid);
    }

    public function test_a_message_queued_before_carriers_existed_uses_the_default(): void
    {
        $this->allCarriersConfigured(['sms_default_provider' => SmsProvider::Bandwidth->value]);

        Http::fake([
            'messaging.bandwidth.com/*' => Http::response(['id' => 'bw-legacy-1'], 202),
        ]);

        $host = $this->host();

        $message = WctpMessage::factory()->queued()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp_legacy',
            'from' => '+15559998888',
            'to' => '+15551234567',
            // No carrier recorded, as for every row written before the column existed.
            'provider' => null,
        ]);

        (new ProcessWctpMessage($message))->handle(app(SmsGatewayManager::class));

        $message->refresh();

        $this->assertSame(SmsProvider::Bandwidth->value, $message->provider);
        $this->assertSame('bw-legacy-1', $message->provider_message_id);
    }

    public function test_a_host_without_numbers_falls_back_to_the_default_carriers_number(): void
    {
        Queue::fake();
        $this->allCarriersConfigured(['sms_default_provider' => SmsProvider::Bandwidth->value]);

        $this->host(['phone_numbers' => null]);

        $this->submit()->assertOk();

        $message = WctpMessage::where('wctp_message_id', 'msg-1')->firstOrFail();

        $this->assertSame('+15552220000', $message->from);
        $this->assertSame(SmsProvider::Bandwidth->value, $message->provider);
    }
}
