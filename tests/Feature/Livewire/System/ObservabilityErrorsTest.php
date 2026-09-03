<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\Observability\Errors;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\TestEventSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Mocks\FakeTestEventSender;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class ObservabilityErrorsTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    private const DSN = 'https://publickey@glitchtip.example.com/3';

    protected function setUp(): void
    {
        parent::setUp();

        ObservabilityConfig::flush();
        FakeTestEventSender::$shouldSucceed = true;
        FakeTestEventSender::$sentTo = [];

        $this->team = $this->createSeededTeam();
    }

    protected function tearDown(): void
    {
        ObservabilityConfig::flush();

        parent::tearDown();
    }

    public function test_it_saves_and_stores_the_dsn_as_ciphertext(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->set('enabled', true)
            ->set('dsn', self::DSN)
            ->set('environment', 'production')
            ->set('sampleRate', 0.5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('saved');

        $settings = Settings::first();
        $this->assertTrue($settings->observability_errors_enabled);
        $this->assertSame(self::DSN, $settings->observability_errors_dsn);
        $this->assertSame('production', $settings->observability_environment);
        $this->assertSame(0.5, $settings->observability_errors_sample_rate);

        $this->assertNotSame(self::DSN, DB::table('settings')->value('observability_errors_dsn'));
    }

    public function test_the_dsn_is_never_placed_in_component_state(): void
    {
        $this->settingsWithDsn();

        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->assertSet('dsn', '')
            ->assertSet('hasDsn', true)
            ->assertDontSee('publickey')
            ->assertSee('••••');
    }

    public function test_a_blank_dsn_on_save_keeps_the_stored_secret(): void
    {
        $this->settingsWithDsn();

        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->set('environment', 'staging')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(self::DSN, Settings::first()->observability_errors_dsn);
    }

    public function test_enabling_without_any_dsn_fails_validation(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->set('enabled', true)
            ->call('save')
            ->assertHasErrors('dsn');

        $this->assertNull(Settings::first());
    }

    public function test_an_invalid_dsn_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->set('dsn', 'not-a-dsn')
            ->call('save')
            ->assertHasErrors('dsn');
    }

    public function test_send_test_event_records_success(): void
    {
        $this->swapSender();
        $this->settingsWithDsn();

        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->call('sendTestEvent')
            ->assertSet('testError', null);

        $this->assertSame([self::DSN], FakeTestEventSender::$sentTo);
        $this->assertSame('ok', Settings::first()->observability_last_test_status);
    }

    public function test_send_test_event_records_failure(): void
    {
        $this->swapSender();
        FakeTestEventSender::$shouldSucceed = false;
        $this->settingsWithDsn();

        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->call('sendTestEvent')
            ->assertSet('testResult', null);

        $this->assertSame('failed', Settings::first()->observability_last_test_status);
    }

    public function test_actions_are_denied_without_the_capability(): void
    {
        // The Integrations components' bug, guarded against here: Livewire does
        // not re-apply the controller's authorize() on POST /livewire/update.
        $this->settingsWithDsn();
        $user = $this->createUserWithout($this->team, 'agent', Capability::SystemObservability);

        try {
            Livewire::actingAs($user)
                ->test(Errors::class)
                ->set('enabled', true)
                ->set('dsn', 'https://attacker@evil.example.com/1')
                ->call('save');
        } catch (\Throwable) {
            // Livewire renders the authorization failure; the property that
            // matters is that nothing was written.
        }

        $settings = Settings::first();
        $this->assertFalse($settings->observability_errors_enabled);
        $this->assertSame(self::DSN, $settings->observability_errors_dsn);
    }

    public function test_the_page_is_forbidden_without_the_capability(): void
    {
        $user = $this->createUserWithout($this->team, 'agent', Capability::SystemObservability);

        $this->actingAs($user)->get(route('system.observability'))->assertForbidden();
    }

    public function test_the_page_renders_for_a_capable_user(): void
    {
        $this->actingAs($this->admin())->get(route('system.observability'))->assertOk();
    }

    public function test_it_warns_when_an_env_override_is_active(): void
    {
        config(['observability.errors.enabled' => false]);
        ObservabilityConfig::flush();

        Livewire::actingAs($this->admin())
            ->test(Errors::class)
            ->assertSet('envOverridden', true)
            ->assertSee('Overridden by environment');
    }

    private function admin(): User
    {
        return $this->createUserWithRole($this->team, 'admin');
    }

    private function settingsWithDsn(): Settings
    {
        $settings = new Settings;
        $settings->observability_errors_dsn = self::DSN;
        $settings->observability_errors_enabled = false;
        $settings->save();

        ObservabilityConfig::flush();

        return $settings;
    }

    private function swapSender(): void
    {
        $this->app->singleton(TestEventSender::class, fn () => new FakeTestEventSender);
    }
}
