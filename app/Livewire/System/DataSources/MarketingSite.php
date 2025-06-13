<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class MarketingSite extends Component
{
    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();
        $this->state['marketing_site'] = $this->datasource->marketing_site ?? '';
    }

    public function saveMarketingWebsite(): void
    {
        $this->validate([
            'state.marketing_site' => 'required|url',
        ], [
            'state.marketing_site' => $this->state['marketing_site'],
        ]);

        $this->datasource->marketing_site = $this->state['marketing_site'];
        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {

        return view('livewire.system.data-sources.marketing-site');
    }
}
