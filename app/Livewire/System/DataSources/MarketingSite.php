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

class MarketingSite extends Component implements HasActions, HasSchemas
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
        return ['marketing_site'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('marketing_site')
                ->label('Marketing Site URL')
                ->url()
                ->required()
                ->placeholder('https://yourdomain.com')
                ->validationAttribute('marketing site URL'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.marketing-site');
    }
}
