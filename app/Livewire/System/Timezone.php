<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsSystemSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class Timezone extends Component implements HasActions, HasSchemas
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
        return ['switch_data_timezone'];
    }

    protected function settingsSchema(): array
    {
        return [
            Select::make('switch_data_timezone')
                ->label('Switch Data Timezone')
                ->options(fn (): array => collect(timezone_identifiers_list())
                    ->mapWithKeys(fn (string $tz): array => [$tz => $tz])
                    ->all())
                ->searchable()
                ->required()
                ->default('UTC')
                ->helperText('The timezone Amtelco records its timestamps in. Analytics converts from it.')
                ->validationAttribute('switch data timezone'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.timezone');
    }
}
