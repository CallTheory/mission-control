<?php

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class IsWebApi extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemDataSources;
    }

    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();
        $this->state['isweb_api_endpoint'] = $this->datasource->is_web_api_endpoint ?? '';
    }

    public function saveISWebAPIConnection(): void
    {
        $this->validate([
            'state.isweb_api_endpoint' => 'required|url',
        ], [
            'state.isweb_api_endpoint' => $this->state['isweb_api_endpoint'],
        ]);

        $this->datasource->is_web_api_endpoint = $this->state['isweb_api_endpoint'];
        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.isweb-api');
    }
}
