<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\Observability\Tracing as TracingComponent;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\TraceEndpointProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class ObservabilityTracingTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        ObservabilityConfig::flush();
        $this->team = $this->createSeededTeam();
    }

    protected function tearDown(): void
    {
        ObservabilityConfig::flush();
        parent::tearDown();
    }

    public function test_it_saves_and_encrypts_the_auth_token(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->set('endpoint', 'http://localhost:4318')
            ->set('authUsername', '123456')
            ->set('authToken', 'glc_secret_token')
            ->set('sampleRate', 0.25)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('saved');

        $settings = Settings::first();
        $this->assertSame('glc_secret_token', $settings->observability_tracing_auth_token);
        $this->assertSame(0.25, $settings->observability_tracing_sample_rate);

        $this->assertNotSame(
            'glc_secret_token',
            DB::table('settings')->value('observability_tracing_auth_token')
        );
    }

    public function test_the_auth_token_is_never_in_component_state(): void
    {
        $settings = new Settings;
        $settings->observability_tracing_auth_token = 'glc_secret_token';
        $settings->save();

        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->assertSet('authToken', '')
            ->assertSet('hasAuthToken', true)
            ->assertDontSee('glc_secret_token');
    }

    public function test_a_blank_token_keeps_the_stored_secret(): void
    {
        $settings = new Settings;
        $settings->observability_tracing_auth_token = 'glc_secret_token';
        $settings->save();

        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->set('serviceName', 'mission-control')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('glc_secret_token', Settings::first()->observability_tracing_auth_token);
    }

    public function test_it_reports_when_no_collector_is_listening(): void
    {
        // The behavior asked for: never assume Alloy is running — say so.
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->set('endpoint', 'http://localhost:4318')
            ->call('checkEndpoint')
            ->assertSet('probeStatus', TraceEndpointProbe::REFUSED)
            ->assertSee('Nothing is listening');
    }

    public function test_it_reports_a_reachable_collector(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->set('endpoint', 'http://localhost:4318')
            ->call('checkEndpoint')
            ->assertSet('probeStatus', TraceEndpointProbe::REACHABLE);
    }

    public function test_it_reports_rejected_credentials(): void
    {
        Http::fake(['*' => Http::response('nope', 401)]);

        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->call('checkEndpoint')
            ->assertSet('probeStatus', TraceEndpointProbe::UNAUTHORIZED);
    }

    public function test_an_invalid_endpoint_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TracingComponent::class)
            ->set('endpoint', 'not a url')
            ->call('save')
            ->assertHasErrors('endpoint');
    }

    public function test_actions_are_denied_without_the_capability(): void
    {
        $user = $this->createUserWithout($this->team, 'agent', Capability::SystemObservability);

        try {
            Livewire::actingAs($user)
                ->test(TracingComponent::class)
                ->set('enabled', true)
                ->set('endpoint', 'http://attacker.example.com:4318')
                ->call('save');
        } catch (\Throwable) {
            // Livewire renders the authorization failure; the write is what matters.
        }

        $this->assertNull(Settings::first());
    }

    private function admin(): User
    {
        return $this->createUserWithRole($this->team, 'admin');
    }
}
