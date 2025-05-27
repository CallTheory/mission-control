<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class AmtelcoSMTP extends Component
{
    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        $this->state['amtelco_inbound_smtp_host'] = $this->datasource->amtelco_inbound_smtp_host ?? '';
        $this->state['amtelco_inbound_smtp_port'] = $this->datasource->amtelco_inbound_smtp_port ?? '';
    }

    public function updateAmtelcoSMTPDetails(): void
    {
        $this->validate([
            'state.amtelco_inbound_smtp_host' => 'required|string',
            'state.amtelco_inbound_smtp_port' => 'required|integer|numeric',
        ], [
            'state.amtelco_inbound_smtp_host' => $this->state['amtelco_inbound_smtp_host'],
            'state.amtelco_inbound_smtp_port' => $this->state['amtelco_inbound_smtp_port'],
        ]);

        $this->datasource->amtelco_inbound_smtp_host = $this->state['amtelco_inbound_smtp_host'];
        $this->datasource->amtelco_inbound_smtp_port = $this->state['amtelco_inbound_smtp_port'];

        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.amtelco-smtp');
    }
}
