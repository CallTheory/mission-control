<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sms;

use App\Enums\SmsProvider;
use App\Models\DataSource;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BandwidthGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function configure(array $overrides = []): DataSource
    {
        return DataSource::create([
            'bandwidth_account_id' => '5000000',
            'bandwidth_application_id' => 'app-123',
            'bandwidth_from_number' => '+15559998888',
            'bandwidth_api_token' => 'api-token',
            'bandwidth_api_secret' => 'api-secret',
            ...$overrides,
        ]);
    }

    private function gateway(): SmsGateway
    {
        return app(SmsGatewayManager::class)->gateway(SmsProvider::Bandwidth);
    }

    public function test_is_not_configured_until_every_credential_is_present(): void
    {
        $this->assertFalse($this->gateway()->isConfigured());

        $this->configure(['bandwidth_api_secret' => null]);

        $this->assertFalse(app(SmsGatewayManager::class)->gateway(SmsProvider::Bandwidth)->isConfigured());
    }

    public function test_sends_a_message_to_the_v2_messaging_api(): void
    {
        $this->configure();

        Http::fake([
            'messaging.bandwidth.com/*' => Http::response([
                'id' => 'bw-message-id',
                'time' => '2026-09-17T12:00:00Z',
            ], 202),
        ]);

        $result = $this->gateway()->sendSms('5551234567', 'Hello there', [
            'from' => '5559998888',
            'messageId' => 'wctp_abc',
            // Twilio-only option: must not leak into the Bandwidth payload.
            'statusCallback' => 'https://example.com/ignored',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('bw-message-id', $result['message_sid']);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://messaging.bandwidth.com/api/v2/users/5000000/messages'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-token:api-secret'))
                && $request['applicationId'] === 'app-123'
                && $request['to'] === ['+15551234567']
                && $request['from'] === '+15559998888'
                && $request['text'] === 'Hello there'
                // The tag is the only handle a delivery receipt gives us back.
                && $request['tag'] === 'wctp_abc'
                && ! array_key_exists('statusCallback', $request->data());
        });
    }

    public function test_reports_an_api_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'messaging.bandwidth.com/*' => Http::response([
                'type' => 'validation',
                'description' => 'from must be a Bandwidth number',
            ], 400),
        ]);

        $result = $this->gateway()->sendSms('5551234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('from must be a Bandwidth number', $result['error']);
    }

    public function test_fails_to_send_when_not_configured(): void
    {
        Http::fake();

        $result = $this->gateway()->sendSms('5551234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_parses_an_inbound_message_event(): void
    {
        $this->configure();

        $request = Request::create('/wctp/sms/bandwidth/incoming', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([[
            'type' => 'message-received',
            'to' => '+15559998888',
            'message' => [
                'id' => 'bw-inbound-1',
                'from' => '+15551234567',
                'to' => ['+15559998888'],
                'text' => 'Reply 1',
                'direction' => 'in',
            ],
        ]]));

        $messages = $this->gateway()->inboundMessages($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+15551234567', $messages[0]->from);
        $this->assertSame('+15559998888', $messages[0]->to);
        $this->assertSame('Reply 1', $messages[0]->body);
        $this->assertSame('bw-inbound-1', $messages[0]->providerMessageId);

        // The same request carries no delivery receipts.
        $this->assertSame([], $this->gateway()->deliveryUpdates($request));
    }

    public function test_parses_delivery_receipt_events_and_correlates_by_tag(): void
    {
        $this->configure();

        $request = Request::create('/wctp/bandwidth/callback', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            [
                'type' => 'message-delivered',
                'message' => ['id' => 'bw-1', 'tag' => 'wctp_one'],
            ],
            [
                'type' => 'message-failed',
                'errorCode' => 4405,
                'description' => 'Rejected by carrier',
                'message' => ['id' => 'bw-2', 'tag' => 'wctp_two'],
            ],
        ]));

        $updates = $this->gateway()->deliveryUpdates($request);

        $this->assertCount(2, $updates);

        $this->assertSame('delivered', $updates[0]->status);
        $this->assertSame('wctp_one', $updates[0]->wctpMessageId);
        $this->assertSame('bw-1', $updates[0]->providerMessageId);

        $this->assertSame('failed', $updates[1]->status);
        $this->assertSame('wctp_two', $updates[1]->wctpMessageId);
        $this->assertStringContainsString('4405', $updates[1]->error);

        $this->assertSame([], $this->gateway()->inboundMessages($request));
    }

    public function test_webhook_fails_closed_with_no_callback_credentials(): void
    {
        $this->configure();

        $request = Request::create('/wctp/sms/bandwidth/incoming', 'POST');

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_webhook_accepts_basic_credentials(): void
    {
        $this->configure([
            'bandwidth_callback_username' => 'hook-user',
            'bandwidth_callback_password' => 'hook-pass',
        ]);

        $good = Request::create('/wctp/sms/bandwidth/incoming', 'POST', [], [], [], [
            'PHP_AUTH_USER' => 'hook-user',
            'PHP_AUTH_PW' => 'hook-pass',
        ]);

        $bad = Request::create('/wctp/sms/bandwidth/incoming', 'POST', [], [], [], [
            'PHP_AUTH_USER' => 'hook-user',
            'PHP_AUTH_PW' => 'wrong',
        ]);

        $this->assertTrue($this->gateway()->verifyWebhook($good));
        $this->assertFalse($this->gateway()->verifyWebhook($bad));
    }

    public function test_webhook_accepts_a_shared_token(): void
    {
        $this->configure(['bandwidth_callback_token' => 'secret-token']);

        $viaQuery = Request::create('/wctp/sms/bandwidth/incoming?token=secret-token', 'POST');

        $viaHeader = Request::create('/wctp/sms/bandwidth/incoming', 'POST');
        $viaHeader->headers->set('X-Callback-Token', 'secret-token');

        $wrong = Request::create('/wctp/sms/bandwidth/incoming?token=nope', 'POST');

        $this->assertTrue($this->gateway()->verifyWebhook($viaQuery));
        $this->assertTrue($this->gateway()->verifyWebhook($viaHeader));
        $this->assertFalse($this->gateway()->verifyWebhook($wrong));
    }
}
