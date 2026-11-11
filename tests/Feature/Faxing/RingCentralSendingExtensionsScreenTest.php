<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Livewire\System\RingCentralSendingExtensions;
use App\Models\DataSource;
use App\Models\RingCentralSender;
use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRateLimitState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\FakesRingCentral;
use Tests\Traits\InteractsWithFeatureFlags;

class RingCentralSendingExtensionsScreenTest extends TestCase
{
    use CreatesTeamUsers;
    use FakesRingCentral;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('cloud-faxing');

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
        ]);

        // Every JWT a test uses is mapped; anything else belongs to an unrelated extension,
        // so a refusal can only come from a genuine match.
        $this->fakeRingCentral([
            'primary-jwt' => '101',
            'another-primary-jwt' => '101',
            'second-jwt' => '102',
            'second-again-jwt' => '102',
        ], defaultOwner: '999');

        $this->actingAs($this->createUserWithRole($this->createSeededTeam(), 'admin'));
    }

    public function test_the_cloud_faxing_settings_page_renders_the_extensions_table(): void
    {
        // Livewire::test() resolves by class, so only rendering the real page proves the
        // tag's component name resolves (RingCentral kebab-cases to ring-central).
        $this->get(route('system.cloud-faxing'))
            ->assertOk()
            ->assertSeeLivewire(RingCentralSendingExtensions::class);
    }

    public function test_adding_an_extension_verifies_the_jwt_and_records_whose_it_is(): void
    {
        Livewire::test(RingCentralSendingExtensions::class)
            ->callTableAction('createSender', data: ['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'sort' => 0, 'enabled' => true])
            ->assertHasNoTableActionErrors();

        $sender = RingCentralSender::sole();

        $this->assertSame('102', $sender->extension_id);
        $this->assertSame('second-jwt', $sender->jwt_token);
        // Encrypted at rest.
        $this->assertNotSame('second-jwt', $sender->getRawOriginal('jwt_token'));
    }

    public function test_a_jwt_for_the_primary_extension_is_refused(): void
    {
        Livewire::test(RingCentralSendingExtensions::class)
            ->callTableAction('createSender', data: ['label' => 'Duplicate', 'jwt_token' => 'another-primary-jwt', 'sort' => 0, 'enabled' => true])
            ->assertNotified('That JWT belongs to an extension already sending faxes.');

        // A second token for the same extension shares that extension's limit.
        $this->assertSame(0, RingCentralSender::count());
    }

    public function test_a_jwt_for_an_extension_already_in_the_pool_is_refused(): void
    {
        RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);
        Livewire::test(RingCentralSendingExtensions::class)
            ->callTableAction('createSender', data: ['label' => 'Fax 2 again', 'jwt_token' => 'second-again-jwt', 'sort' => 0, 'enabled' => true])
            ->assertNotified('That JWT belongs to an extension already sending faxes.');

        $this->assertSame(1, RingCentralSender::count());
    }

    public function test_an_unrelated_extension_is_accepted(): void
    {
        Livewire::test(RingCentralSendingExtensions::class)
            ->callTableAction('createSender', data: ['label' => 'Fax 9', 'jwt_token' => 'some-other-jwt', 'sort' => 0, 'enabled' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame('999', RingCentralSender::sole()->extension_id);
    }

    public function test_editing_with_a_blank_jwt_keeps_the_stored_one(): void
    {
        $sender = RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);

        Livewire::test(RingCentralSendingExtensions::class)
            ->callTableAction('editSender', $sender, data: ['label' => 'Fax 2 (front desk)', 'jwt_token' => '', 'sort' => 1, 'enabled' => false])
            ->assertHasNoTableActionErrors();

        $sender->refresh();

        $this->assertSame('Fax 2 (front desk)', $sender->label);
        $this->assertSame('second-jwt', $sender->jwt_token);
        $this->assertFalse($sender->enabled);
    }

    public function test_the_screen_shows_each_extensions_live_rate_limit(): void
    {
        $sender = RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);
        app(RingCentralRateLimitState::class)->record('primary', new RingCentralRateLimit(status: 200, limit: 10, remaining: 4, window: 60));
        app(RingCentralRateLimitState::class)->record($sender->senderKey(), new RingCentralRateLimit(status: 429, retryAfter: 45));

        Livewire::test(RingCentralSendingExtensions::class)
            ->assertSee('4 of 10 left per 60s')
            ->assertSee('Locked out for');
    }

    public function test_deleting_an_extension_removes_it(): void
    {
        $sender = RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);

        Livewire::test(RingCentralSendingExtensions::class)->callTableAction('deleteSender', $sender);

        $this->assertSame(0, RingCentralSender::count());
    }

    public function test_a_user_without_the_integrations_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithRole($this->createSeededTeam(), 'agent'));

        Livewire::test(RingCentralSendingExtensions::class)->assertForbidden();
    }
}
