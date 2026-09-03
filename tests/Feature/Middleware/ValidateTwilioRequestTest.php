<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\ValidateTwilioRequest;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class ValidateTwilioRequestTest extends TestCase
{
    use RefreshDatabase;

    private function pass(Request $request): Response
    {
        return (new ValidateTwilioRequest)->handle($request, fn () => new Response('ok', 200));
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

    public function test_fails_closed_when_data_source_exists_without_auth_token(): void
    {
        config(['wctp.validate_twilio_signatures' => true]);

        DataSource::create(['twilio_account_sid' => 'AC-test']);

        $request = Request::create('/wctp/sms/incoming', 'POST', ['Body' => 'hi']);
        $request->headers->set('X-Twilio-Signature', 'anything');

        $this->assertSame(403, $this->pass($request)->getStatusCode());
    }

    public function test_rejects_an_incorrectly_signed_request(): void
    {
        config(['wctp.validate_twilio_signatures' => true]);

        DataSource::create(['twilio_auth_token' => 'super-secret-token']);

        $request = Request::create('/wctp/sms/incoming', 'POST', ['Body' => 'hi']);
        $request->headers->set('X-Twilio-Signature', 'not-the-right-signature');

        $this->assertSame(403, $this->pass($request)->getStatusCode());
    }

    public function test_allows_a_correctly_signed_request(): void
    {
        // The path that was previously unreachable: before the DataSource query
        // was fixed this blew up on an unknown `type` column rather than
        // validating the signature.
        config(['wctp.validate_twilio_signatures' => true]);

        DataSource::create(['twilio_auth_token' => 'super-secret-token']);

        $params = ['Body' => 'hi', 'From' => '+15555550123'];
        $request = Request::create('/wctp/sms/incoming', 'POST', $params);

        $signature = (new RequestValidator('super-secret-token'))
            ->computeSignature($request->fullUrl(), $params);
        $request->headers->set('X-Twilio-Signature', $signature);

        $this->assertSame(200, $this->pass($request)->getStatusCode());
    }

    public function test_reads_the_auth_token_through_the_encrypted_cast(): void
    {
        DataSource::create(['twilio_auth_token' => 'super-secret-token']);

        // Ciphertext at rest, plaintext through the cast — the middleware used
        // to look for a non-existent `credentials` attribute instead.
        $this->assertNotSame(
            'super-secret-token',
            DB::table('data_sources')->value('twilio_auth_token')
        );
        $this->assertSame('super-secret-token', DataSource::first()->twilio_auth_token);
    }
}
