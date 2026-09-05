<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Livewire\Profile\UserTheme;
use App\Livewire\System\DataSources\ClientDb;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class SettingsFormsTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These components are gated by AuthorizesSystemComponent.
        $this->actingAs($this->createUserWithRole($this->createSeededTeam(), 'admin'));
    }

    /*
     * The Twilio cases that lived here drove the component's public $state and a
     * save() method, both of which the Filament schema conversion removed. They are
     * covered against the action API in
     * Tests\Feature\Livewire\System\IntegrationSettingsTest, including the
     * encrypted-at-rest assertion.
     */

    public function test_client_db_form_renders(): void
    {
        DataSource::create(['client_db_host' => 'db.example', 'client_db_port' => '1433']);

        Livewire::test(ClientDb::class)
            ->assertSuccessful()
            ->assertSet('state.client_db_host', 'db.example')
            ->assertSet('state.client_db_pass', ''); // password never prefilled
    }

    public function test_user_theme_toggle_persists_dark_mode(): void
    {
        $user = User::factory()->create(['dark_mode' => null]);
        $this->actingAs($user);

        Livewire::test(UserTheme::class)
            ->set('state.user_theme', 'dark')
            ->call('updateUserTheme')
            ->assertDispatched('saved');

        $this->assertSame('dark', $user->fresh()->dark_mode);
    }
}
