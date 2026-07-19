<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\API\Agents\InboundEmail\ForwardController;
use App\Jobs\InboundRuleMatch;
use App\Mail\ForwardInboundEmail;
use App\Models\InboundEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundEmailWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the request pipeline off Redis/throttle in tests.
        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->withoutMiddleware(ThrottleRequests::class);

        // The forward route is registered only when the inbound-email feature is
        // enabled at boot; register it directly for the test.
        Route::middleware('api')->prefix('api')->group(function () {
            Route::post('/agents/inbound-email/forward/{email}', ForwardController::class)
                ->name('api.agents.inbound-email.forward');
        });

        // Enable the inbound-email system feature (used by the parse endpoint).
        Storage::makeDirectory('feature-flags');
        Storage::put('feature-flags/inbound-email.flag', encrypt('inbound-email'));
    }

    protected function tearDown(): void
    {
        Storage::delete('feature-flags/inbound-email.flag');

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Forward endpoint
    // ------------------------------------------------------------------

    public function test_forward_rejects_missing_secret(): void
    {
        Mail::fake();
        config(['services.inbound_email.forward_secret' => 'correct-horse-battery-staple']);
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);

        $this->postJson("/api/agents/inbound-email/forward/{$email->id}", [
            'email' => 'attacker@evil.com',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_forward_rejects_wrong_secret(): void
    {
        Mail::fake();
        config(['services.inbound_email.forward_secret' => 'correct-horse-battery-staple']);
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);

        $this->postJson("/api/agents/inbound-email/forward/{$email->id}", [
            'api_key' => 'wrong',
            'email' => 'attacker@evil.com',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_forward_fails_closed_when_secret_unconfigured(): void
    {
        Mail::fake();
        config(['services.inbound_email.forward_secret' => null]);
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);

        // An empty guessable "" must never authenticate, even if the caller sends "".
        $this->postJson("/api/agents/inbound-email/forward/{$email->id}", [
            'api_key' => '',
            'email' => 'attacker@evil.com',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_forward_accepts_valid_secret(): void
    {
        Mail::fake();
        config(['services.inbound_email.forward_secret' => 'correct-horse-battery-staple']);
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);

        $this->postJson("/api/agents/inbound-email/forward/{$email->id}", [
            'api_key' => 'correct-horse-battery-staple',
            'email' => 'agent@example.com',
        ])->assertOk();

        Mail::assertQueued(ForwardInboundEmail::class);
    }

    // ------------------------------------------------------------------
    // SendGrid parse endpoint
    // ------------------------------------------------------------------

    public function test_parse_rejects_wrong_secret(): void
    {
        Queue::fake();
        config(['services.inbound_email.parse_secret' => 'parse-secret-value']);

        $this->postJson('/webhooks/sendgrid/parse/not-the-secret', [
            'to' => 'x@y.com',
            'from' => 'spoofed@evil.com',
        ])->assertNotFound();

        Queue::assertNotPushed(InboundRuleMatch::class);
    }

    public function test_parse_fails_closed_when_secret_unconfigured(): void
    {
        Queue::fake();
        config(['services.inbound_email.parse_secret' => null]);

        $this->postJson('/webhooks/sendgrid/parse/', [
            'to' => 'x@y.com',
            'from' => 'spoofed@evil.com',
        ])->assertNotFound();

        // Also with an explicit empty key segment style value.
        $this->postJson('/webhooks/sendgrid/parse/anything', [
            'to' => 'x@y.com',
        ])->assertNotFound();

        Queue::assertNotPushed(InboundRuleMatch::class);
    }

    public function test_parse_accepts_valid_secret(): void
    {
        Queue::fake();
        config(['services.inbound_email.parse_secret' => 'parse-secret-value']);

        $this->postJson('/webhooks/sendgrid/parse/parse-secret-value', [
            'to' => 'inbox@example.com',
            'from' => 'sender@example.com',
            'subject' => 'Hello',
            'attachments' => 0,
        ])->assertOk();

        Queue::assertPushed(InboundRuleMatch::class);
    }
}
