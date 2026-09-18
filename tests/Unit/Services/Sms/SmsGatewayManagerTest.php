<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sms;

use App\Enums\SmsProvider;
use App\Models\DataSource;
use App\Services\Sms\Gateways\BandwidthGateway;
use App\Services\Sms\Gateways\CommioGateway;
use App\Services\Sms\Gateways\TwilioGateway;
use App\Services\Sms\SmsGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SmsGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): SmsGatewayManager
    {
        return app(SmsGatewayManager::class);
    }

    public function test_resolves_a_gateway_for_every_carrier(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(TwilioGateway::class, $manager->gateway(SmsProvider::Twilio));
        $this->assertInstanceOf(BandwidthGateway::class, $manager->gateway('bandwidth'));
        $this->assertInstanceOf(CommioGateway::class, $manager->gateway('commio'));
    }

    public function test_rejects_an_unknown_carrier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager()->gateway('carrierpigeon');
    }

    public function test_defaults_to_twilio_when_nothing_is_chosen(): void
    {
        $this->assertSame(SmsProvider::Twilio, $this->manager()->defaultProvider());

        DataSource::create(['sms_default_provider' => null]);

        $this->assertSame(SmsProvider::Twilio, app(SmsGatewayManager::class)->defaultProvider());
    }

    public function test_honours_the_stored_default(): void
    {
        DataSource::create(['sms_default_provider' => SmsProvider::Bandwidth->value]);

        $manager = app(SmsGatewayManager::class);

        $this->assertSame(SmsProvider::Bandwidth, $manager->defaultProvider());
        $this->assertInstanceOf(BandwidthGateway::class, $manager->default());
    }

    public function test_a_stored_value_that_is_no_longer_a_carrier_falls_back(): void
    {
        DataSource::create(['sms_default_provider' => 'retired-carrier']);

        $this->assertSame(SmsProvider::Twilio, app(SmsGatewayManager::class)->defaultProvider());
    }

    public function test_configured_lists_only_carriers_that_can_send(): void
    {
        DataSource::create([
            'commio_account_id' => '4321',
            'commio_username' => 'portal-user',
            'commio_api_token' => 'portal-token',
            'commio_from_number' => '+15553330000',
        ]);

        $this->assertSame(['commio'], array_keys(app(SmsGatewayManager::class)->configured()));
        $this->assertCount(3, app(SmsGatewayManager::class)->all());
    }
}
