<?php

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class MiteamWeb extends Component
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
        $this->state['miteamweb_site'] = $this->datasource->miteamweb_site ?? '';
    }

    public function saveMiTeamWeb(): void
    {
        $this->validate([
            'state.miteamweb_site' => 'required|url',
        ], [
            'state.miteamweb_site' => $this->state['miteamweb_site'],
        ]);

        $this->datasource->miteamweb_site = $this->state['miteamweb_site'];
        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.miteamweb');
    }
}
