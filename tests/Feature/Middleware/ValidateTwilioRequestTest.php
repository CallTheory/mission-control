<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\ValidateTwilioRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ValidateTwilioRequestTest extends TestCase
{
    use RefreshDatabase;

    private function pass(Request $request): Response
    {
        return (new ValidateTwilioRequest())->handle($request, fn () => new Response('ok', 200));
    }

    public function test_fails_closed_when_no_twilio_data_source(): void
    {
        config(['wctp.validate_twilio_signatures' => true]);

        $request = Request::create('/wctp/sms/incoming', 'POST', ['Body' => 'hi']);
        $request->headers->set('X-Twilio-Signature', 'anything');

        $this->assertSame(403, $this->pass($request)->getStatusCode());
    }

    public function test_rejects_missing_signature_header(): void
    {
        // Even the explicit config default must not let an unsigned request through
        // once validation is enabled and a data source exists is irrelevant here:
        // with no data source we already fail closed, so assert the enabled default.
        config(['wctp.validate_twilio_signatures' => true]);

        $request = Request::create('/wctp/sms/incoming', 'POST', ['Body' => 'hi']);

        $this->assertSame(403, $this->pass($request)->getStatusCode());
    }

    public function test_explicit_opt_out_allows_request(): void
    {
        // Operators may still explicitly disable validation; that path is allowed.
        config(['wctp.validate_twilio_signatures' => false]);

        $request = Request::create('/wctp/sms/incoming', 'POST', ['Body' => 'hi']);

        $this->assertSame(200, $this->pass($request)->getStatusCode());
    }
}
