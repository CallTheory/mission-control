<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ShowsIntegrationInfo;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Bandwidth extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ShowsIntegrationInfo;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    protected function infoHeading(): string
    {
        return 'Bandwidth';
    }

    protected function infoContent(): View
    {
        return view('livewire.system.integrations.partials.bandwidth');
    }

    public function render(): View
    {
        return view('livewire.system.integrations.bandwidth');
    }
}
