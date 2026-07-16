<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\System\CloudFaxingProviders;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CloudFaxingProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_reflects_existing_enabled_state_and_configuration(): void
    {
        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_enabled' => true,
            'mfax_api_key' => encrypt('key'),
            'mfax_enabled' => false,
        ]);

        Livewire::test(CloudFaxingProviders::class)
            ->assertSet('ringcentral_enabled', true)
            ->assertSet('ringcentral_configured', true)
            ->assertSet('mfax_enabled', false)
            ->assertSet('mfax_configured', true);
    }

    public function test_it_toggles_ringcentral_without_touching_credentials(): void
    {
        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_enabled' => true,
        ]);

        Livewire::test(CloudFaxingProviders::class)
            ->call('toggleRingCentral')
            ->assertSet('ringcentral_enabled', false)
            ->assertDispatched('saved');

        $datasource = DataSource::first();
        $this->assertFalse($datasource->ringcentral_enabled);
        // Credentials are preserved after disabling.
        $this->assertSame('client-id', $datasource->ringcentral_client_id);
    }

    public function test_it_toggles_mfax_on(): void
    {
        DataSource::create(['mfax_enabled' => false]);

        Livewire::test(CloudFaxingProviders::class)
            ->call('toggleMfax')
            ->assertSet('mfax_enabled', true);

        $this->assertTrue(DataSource::first()->mfax_enabled);
    }

    public function test_it_creates_a_datasource_row_when_none_exists(): void
    {
        $this->assertNull(DataSource::first());

        Livewire::test(CloudFaxingProviders::class)
            ->call('toggleRingCentral')
            ->assertSet('ringcentral_enabled', true);

        $this->assertTrue(DataSource::first()->ringcentral_enabled);
    }
}
