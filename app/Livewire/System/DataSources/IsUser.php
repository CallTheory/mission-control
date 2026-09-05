<?php

declare(strict_types=1);

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsDataSourceSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class IsUser extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use EditsDataSourceSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemDataSources;
    }

    protected function settingsFields(): array
    {
        return ['is_agent_username', 'is_agent_password'];
    }

    /**
     * The password is never sent to the browser and is only written when retyped.
     */
    protected function preservedFields(): array
    {
        return ['is_agent_password'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('is_agent_username')
                ->label('Intelligent Series Agent Username')
                ->required()
                ->validationAttribute('username'),

            TextInput::make('is_agent_password')
                ->label('Intelligent Series Agent Password')
                ->password()
                ->revealable()
                ->required()
                ->confirmed()
                ->validationAttribute('password and confirmation')
                ->helperText('Retype the password to save. It is never displayed.'),

            TextInput::make('is_agent_password_confirmation')
                ->label('Password Confirmation')
                ->password()
                ->revealable()
                ->required()
                // Confirmation is a UI concern only; it is not a column.
                ->dehydrated(false),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.is-user');
    }
}
