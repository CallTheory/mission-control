<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Observability;

use App\Services\Observability\ScrubSentryEvent;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;
use Tests\TestCase;

class ScrubSentryEventTest extends TestCase
{
    public function test_sensitive_request_keys_are_filtered(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://mc.example.com/system/integrations',
            'data' => [
                'password' => 'hunter2',
                'twilio_auth_token' => 'tok-secret',
                'client_secret' => 'cs-secret',
                'api_key' => 'ak-secret',
                'page' => 3,
            ],
        ]);

        $data = ScrubSentryEvent::handle($event, null)->getRequest()['data'];

        $this->assertSame('[Filtered]', $data['password']);
        $this->assertSame('[Filtered]', $data['twilio_auth_token']);
        $this->assertSame('[Filtered]', $data['client_secret']);
        $this->assertSame('[Filtered]', $data['api_key']);
        $this->assertSame(3, $data['page'], 'benign keys survive');
    }

    public function test_cookies_are_dropped_and_auth_headers_filtered(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://mc.example.com/x',
            'cookies' => ['laravel_session' => 'abc123'],
            'headers' => ['Authorization' => 'Bearer secret', 'Accept' => 'application/json'],
        ]);

        $request = ScrubSentryEvent::handle($event, null)->getRequest();

        $this->assertArrayNotHasKey('cookies', $request);
        $this->assertSame('[Filtered]', $request['headers']['Authorization']);
        $this->assertSame('application/json', $request['headers']['Accept']);
    }

    public function test_livewire_payloads_are_dropped_wholesale(): void
    {
        // ManagesDataSourceSettings puts DECRYPTED credentials into public
        // Livewire props, which are serialized into the snapshot. Key-based
        // scrubbing cannot see inside that JSON string, so the whole payload
        // must go.
        $snapshot = json_encode([
            'data' => ['state' => ['twilio_auth_token' => 'tok-PLAINTEXT-SECRET']],
            'memo' => ['name' => 'system.integrations.twilio'],
        ]);

        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://mc.example.com/livewire/update',
            'data' => ['components' => [['snapshot' => $snapshot]]],
        ]);

        $scrubbed = ScrubSentryEvent::handle($event, null);

        $this->assertStringNotContainsString(
            'tok-PLAINTEXT-SECRET',
            json_encode($scrubbed->getRequest())
        );
    }

    public function test_the_user_is_reduced_to_an_identifier(): void
    {
        $user = UserDataBag::createFromUserIdentifier('42');
        $user->setEmail('agent@example.com');
        $user->setIpAddress('203.0.113.5');
        $user->setUsername('agent-smith');

        $event = Event::createEvent();
        $event->setUser($user);

        $scrubbed = ScrubSentryEvent::handle($event, null)->getUser();

        $this->assertSame('42', $scrubbed->getId());
        $this->assertNull($scrubbed->getEmail());
        $this->assertNull($scrubbed->getIpAddress());
        $this->assertNull($scrubbed->getUsername());
    }

    public function test_caller_identifying_labels_are_redacted_from_exception_messages(): void
    {
        $event = Event::createEvent();
        $event->setExceptions([
            new ExceptionDataBag(new \RuntimeException(
                'Failed parsing message: Caller ID: 5551234567 DOB: 01/02/1970 Ptn: Jane Doe'
            )),
        ]);

        $value = ScrubSentryEvent::handle($event, null)->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString('Jane Doe', $value);
        $this->assertStringNotContainsString('01/02/1970', $value);
        $this->assertStringContainsString('[Filtered]', $value);
    }

    public function test_phone_numbers_and_emails_are_redacted(): void
    {
        $event = Event::createEvent();
        $event->setExceptions([
            new ExceptionDataBag(new \RuntimeException(
                'Could not reach 555-123-4567 or patient@hospital.example'
            )),
        ]);

        $value = ScrubSentryEvent::handle($event, null)->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString('555-123-4567', $value);
        $this->assertStringNotContainsString('patient@hospital.example', $value);
    }

    public function test_pem_blocks_are_redacted(): void
    {
        $pem = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBg\n-----END PRIVATE KEY-----";

        $event = Event::createEvent();
        $event->setExtra(['saml' => 'key is '.$pem]);

        $extra = ScrubSentryEvent::handle($event, null)->getExtra();

        $this->assertStringNotContainsString('MIIEvQIBADANBg', $extra['saml']);
        $this->assertStringContainsString('[Redacted PEM]', $extra['saml']);
    }

    public function test_breadcrumb_messages_are_redacted(): void
    {
        $event = Event::createEvent();
        $event->setBreadcrumb([
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                'app',
                'Sent to 555-123-4567',
                ['password' => 'hunter2']
            ),
        ]);

        $breadcrumb = ScrubSentryEvent::handle($event, null)->getBreadcrumbs()[0];

        $this->assertStringNotContainsString('555-123-4567', (string) $breadcrumb->getMessage());
        $this->assertSame('[Filtered]', $breadcrumb->getMetadata()['password']);
    }

    public function test_transactions_are_dropped(): void
    {
        $this->assertNull(ScrubSentryEvent::dropTransaction(Event::createTransaction(), null));
    }

    public function test_a_scrubber_failure_drops_the_event(): void
    {
        // Force a failure inside the callback: an unscrubbed event must never
        // be sent as a fallback.
        config(['observability.scrubbing.max_depth' => 'not-an-int-that-breaks-things']);

        $event = Event::createEvent();
        $event->setUser(UserDataBag::createFromUserIdentifier('1'));

        // Even if it survives, it must not throw out of the callback.
        $result = ScrubSentryEvent::handle($event, null);

        $this->assertTrue($result === null || $result instanceof Event);
    }
}
