<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\SAML2;

use App\Http\Controllers\SAML2\CallbackController;
use Exception;
use Tests\TestCase;

class CallbackControllerTest extends TestCase
{
    private function invoke(string $method, array $args): mixed
    {
        $controller = new CallbackController();
        $ref = new \ReflectionMethod($controller, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($controller, $args);
    }

    /** Build a stub Socialite user whose assertion advertises the given audiences. */
    private function samlUserWithAudiences(?array $audiences): object
    {
        $conditions = $audiences === null ? null : new class($audiences)
        {
            public function __construct(private array $audiences) {}

            public function getAllAudienceRestrictions(): array
            {
                return [new class($this->audiences)
                {
                    public function __construct(private array $audiences) {}

                    public function getAllAudience(): array
                    {
                        return $this->audiences;
                    }
                }];
            }
        };

        $assertion = new class($conditions)
        {
            public function __construct(private ?object $conditions) {}

            public function getConditions(): ?object
            {
                return $this->conditions;
            }
        };

        return new class($assertion)
        {
            public function __construct(private object $assertion) {}

            public function getAssertion(): object
            {
                return $this->assertion;
            }
        };
    }

    // ---------------- audience ----------------

    public function test_audience_matching_sp_passes(): void
    {
        $user = $this->samlUserWithAudiences(['https://sp.example/sso/saml2']);

        $this->invoke('validateAudience', [$user, 'https://sp.example/sso/saml2']);
        $this->assertTrue(true); // no exception
    }

    public function test_audience_for_another_sp_is_rejected(): void
    {
        $user = $this->samlUserWithAudiences(['https://other-sp.example/sso/saml2']);

        $this->expectException(Exception::class);
        $this->invoke('validateAudience', [$user, 'https://sp.example/sso/saml2']);
    }

    public function test_missing_audience_is_allowed_by_default(): void
    {
        config(['services.saml2.require_audience' => false]);
        $user = $this->samlUserWithAudiences(null);

        $this->invoke('validateAudience', [$user, 'https://sp.example/sso/saml2']);
        $this->assertTrue(true);
    }

    public function test_missing_audience_is_rejected_when_required(): void
    {
        config(['services.saml2.require_audience' => true]);
        $user = $this->samlUserWithAudiences(null);

        $this->expectException(Exception::class);
        $this->invoke('validateAudience', [$user, 'https://sp.example/sso/saml2']);
    }

    // ---------------- email domain allow-list ----------------

    public function test_any_domain_allowed_when_list_empty(): void
    {
        config(['services.saml2.allowed_email_domains' => null]);

        $this->assertTrue($this->invoke('emailDomainAllowed', ['anyone@whatever.com']));
    }

    public function test_allowed_domain_passes(): void
    {
        config(['services.saml2.allowed_email_domains' => 'example.com, corp.example']);

        $this->assertTrue($this->invoke('emailDomainAllowed', ['user@example.com']));
        $this->assertTrue($this->invoke('emailDomainAllowed', ['user@CORP.example']));
    }

    public function test_disallowed_domain_blocked(): void
    {
        config(['services.saml2.allowed_email_domains' => 'example.com']);

        $this->assertFalse($this->invoke('emailDomainAllowed', ['user@evil.com']));
        $this->assertFalse($this->invoke('emailDomainAllowed', ['malformed-no-domain']));
    }
}
