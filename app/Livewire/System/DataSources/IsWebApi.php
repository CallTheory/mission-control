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

class IsWebApi extends Component implements HasActions, HasSchemas
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
        return ['is_web_api_endpoint'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('is_web_api_endpoint')
                ->label('Endpoint URL')
                ->url()
                ->required()
                ->placeholder('https://yourdomain.com/isweb/mobileIS.svc')
                ->helperText('The https ISWeb endpoint for your mobileIS.svc.')
                ->validationAttribute('Intelligent Series web API endpoint'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.isweb-api');
    }
}
