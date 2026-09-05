<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class PeoplePraise extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use ConfiguresDataSource;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    protected function settingsFields(): array
    {
        return ['people_praise_basic_auth_user', 'people_praise_basic_auth_pass'];
    }

    protected function settingsHeading(): string
    {
        return 'People Praise Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'Basic auth credentials the board check export presents to People Praise.';
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('people_praise_basic_auth_user')
                ->label('Username')
                ->maxLength(255),

            TextInput::make('people_praise_basic_auth_pass')
                ->label('Password')
                ->password()
                ->revealable()
                ->maxLength(255),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.integrations.people-praise');
    }
}
