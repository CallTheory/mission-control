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

class CommioGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function configure(array $overrides = []): DataSource
    {
        return DataSource::create([
            'commio_account_id' => '4321',
            'commio_username' => 'portal-user',
            'commio_api_token' => 'portal-token',
            'commio_from_number' => '+15559998888',
            ...$overrides,
        ]);
    }

    private function gateway(): SmsGateway
    {
        return app(SmsGatewayManager::class)->gateway(SmsProvider::Commio);
    }

    public function test_sends_a_message_to_the_origination_api_with_bare_digits(): void
    {
        $this->configure();

        Http::fake([
            'api.thinq.com/*' => Http::response(['guid' => 'commio-guid-1'], 200),
        ]);

        $result = $this->gateway()->sendSms('(555) 123-4567', 'Hello there', [
            'from' => '+15559998888',
            'messageId' => 'wctp_abc',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('commio-guid-1', $result['message_sid']);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://api.thinq.com/account/4321/product/origination/sms/send'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('portal-user:portal-token'))
                // thinQ wants digits, not E.164.
                && $request['from_did'] === '15559998888'
                && $request['to_did'] === '15551234567'
                && $request['message'] === 'Hello there';
        });
    }

    public function test_treats_a_response_without_a_guid_as_a_failure(): void
    {
        $this->configure();

        Http::fake(['api.thinq.com/*' => Http::response([], 200)]);

        $result = $this->gateway()->sendSms('5551234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no message guid', $result['error']);
    }

    public function test_reports_an_api_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake(['api.thinq.com/*' => Http::response(['message' => 'Invalid from_did'], 422)]);

        $result = $this->gateway()->sendSms('5551234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid from_did', $result['error']);
    }

    public function test_parses_a_form_encoded_inbound_message(): void
    {
        $this->configure();

        $request = Request::create('/wctp/sms/commio/incoming', 'POST', [
            'from' => '5551234567',
            'to' => '5559998888',
            'message' => 'Reply 2',
            'guid' => 'commio-inbound-1',
        ]);

        $messages = $this->gateway()->inboundMessages($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+15551234567', $messages[0]->from);
        $this->assertSame('+15559998888', $messages[0]->to);
        $this->assertSame('Reply 2', $messages[0]->body);
        $this->assertSame('commio-inbound-1', $messages[0]->providerMessageId);
    }

    public function test_accepts_the_alternate_field_spellings(): void
    {
        $this->configure();

        $request = Request::create('/wctp/sms/commio/incoming', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'from_did' => '5551234567',
            'to_did' => '5559998888',
            'text' => 'Reply 3',
            'id' => 'commio-inbound-2',
        ]));

        $messages = $this->gateway()->inboundMessages($request);

        $this->assertCount(1, $messages);
        $this->assertSame('Reply 3', $messages[0]->body);
        $this->assertSame('commio-inbound-2', $messages[0]->providerMessageId);
    }

    public function test_a_delivery_receipt_is_not_mistaken_for_an_inbound_message(): void
    {
        $this->configure();

        $request = Request::create('/wctp/commio/callback', 'POST', [
            'guid' => 'commio-guid-1',
            'status' => 'DELIVRD',
            'from' => '5559998888',
            'to' => '5551234567',
        ]);

        $this->assertSame([], $this->gateway()->inboundMessages($request));

        $updates = $this->gateway()->deliveryUpdates($request);

        $this->assertCount(1, $updates);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertSame('commio-guid-1', $updates[0]->providerMessageId);
        // thinQ has no tag, so the message is found by its guid instead.
        $this->assertNull($updates[0]->wctpMessageId);
    }

    public function test_maps_a_failure_receipt_with_its_reason(): void
    {
        $this->configure();

        $request = Request::create('/wctp/commio/callback', 'POST', [
            'guid' => 'commio-guid-2',
            'state' => 'undeliv',
            'error' => 'Unknown subscriber',
        ]);

        $updates = $this->gateway()->deliveryUpdates($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('Unknown subscriber', $updates[0]->error);
    }

    public function test_webhook_fails_closed_with_no_callback_credentials(): void
    {
        $this->configure();

        $this->assertFalse($this->gateway()->verifyWebhook(
            Request::create('/wctp/sms/commio/incoming', 'POST')
        ));
    }

    public function test_webhook_accepts_a_shared_token_or_basic_credentials(): void
    {
        $this->configure([
            'commio_callback_username' => 'hook-user',
            'commio_callback_password' => 'hook-pass',
            'commio_callback_token' => 'secret-token',
        ]);

        $viaToken = Request::create('/wctp/sms/commio/incoming?token=secret-token', 'POST');

        $viaBasic = Request::create('/wctp/sms/commio/incoming', 'POST', [], [], [], [
            'PHP_AUTH_USER' => 'hook-user',
            'PHP_AUTH_PW' => 'hook-pass',
        ]);

        $this->assertTrue($this->gateway()->verifyWebhook($viaToken));
        $this->assertTrue($this->gateway()->verifyWebhook($viaBasic));
        $this->assertFalse($this->gateway()->verifyWebhook(
            Request::create('/wctp/sms/commio/incoming', 'POST')
        ));
    }
}
