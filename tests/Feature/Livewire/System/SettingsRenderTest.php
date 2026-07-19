<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every migrated System settings form must still mount and render after the
 * x-form-field / token migration.
 */
class SettingsRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DataSource::create([]); // single shared row the forms edit.
    }

    public static function componentProvider(): array
    {
        return [
            'integrations/Stripe' => [\App\Livewire\System\Integrations\Stripe::class],
            'integrations/Mfax' => [\App\Livewire\System\Integrations\Mfax::class],
            'integrations/Ringcentral' => [\App\Livewire\System\Integrations\Ringcentral::class],
            'integrations/PeoplePraise' => [\App\Livewire\System\Integrations\PeoplePraise::class],
            'integrations/Sendgrid' => [\App\Livewire\System\Integrations\Sendgrid::class],
            'datasources/Intelligent' => [\App\Livewire\System\DataSources\Intelligent::class],
            'datasources/IsUser' => [\App\Livewire\System\DataSources\IsUser::class],
            'datasources/AmtelcoSMTP' => [\App\Livewire\System\DataSources\AmtelcoSMTP::class],
            'datasources/MiteamWeb' => [\App\Livewire\System\DataSources\MiteamWeb::class],
            'datasources/MarketingSite' => [\App\Livewire\System\DataSources\MarketingSite::class],
            'datasources/IsWebApi' => [\App\Livewire\System\DataSources\IsWebApi::class],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_settings_component_renders(string $component): void
    {
        Livewire::test($component)->assertSuccessful();
    }
}
