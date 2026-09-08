<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsSystemSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class BoardCheck extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use EditsSystemSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    protected function settingsFields(): array
    {
        return ['board_check_starting_msgId', 'board_check_people_praise_export_method'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('board_check_starting_msgId')
                ->label('Starting Message ID')
                ->numeric()
                ->helperText('Board check begins filling from this message ID. Leave blank to continue from the newest checked message.')
                ->validationAttribute('starting message ID'),

            Select::make('board_check_people_praise_export_method')
                ->label('People Praise Export Method')
                ->options(['file' => 'File drop', 'api' => 'People Praise API'])
                ->placeholder('Not configured')
                ->helperText('How verified board check results reach People Praise.')
                ->validationAttribute('export method'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.board-check');
    }
}
