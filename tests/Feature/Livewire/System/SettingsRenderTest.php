<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Livewire\System\DataSources\AmtelcoSMTP;
use App\Livewire\System\DataSources\Intelligent;
use App\Livewire\System\DataSources\IsUser;
use App\Livewire\System\DataSources\IsWebApi;
use App\Livewire\System\DataSources\MarketingSite;
use App\Livewire\System\DataSources\MiteamWeb;
use App\Livewire\System\Integrations\Mfax;
use App\Livewire\System\Integrations\PeoplePraise;
use App\Livewire\System\Integrations\Ringcentral;
use App\Livewire\System\Integrations\Sendgrid;
use App\Livewire\System\Integrations\Stripe;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * Every migrated System settings form must still mount and render after the
 * x-form-field / token migration.
 */
class SettingsRenderTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DataSource::create([]); // single shared row the forms edit.

        // These components are gated by AuthorizesSystemComponent, so they need
        // an authenticated user holding the relevant System capability.
        $this->actingAs($this->createUserWithRole($this->createSeededTeam(), 'admin'));
    }

    public static function componentProvider(): array
    {
        return [
            'integrations/Stripe' => [Stripe::class],
            'integrations/Mfax' => [Mfax::class],
            'integrations/Ringcentral' => [Ringcentral::class],
            'integrations/PeoplePraise' => [PeoplePraise::class],
            'integrations/Sendgrid' => [Sendgrid::class],
            'datasources/Intelligent' => [Intelligent::class],
            'datasources/IsUser' => [IsUser::class],
            'datasources/AmtelcoSMTP' => [AmtelcoSMTP::class],
            'datasources/MiteamWeb' => [MiteamWeb::class],
            'datasources/MarketingSite' => [MarketingSite::class],
            'datasources/IsWebApi' => [IsWebApi::class],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_settings_component_renders(string $component): void
    {
        Livewire::test($component)->assertSuccessful();
    }
}
