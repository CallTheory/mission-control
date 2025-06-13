<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class ClientDb extends Component
{
    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        $this->state['client_db_host'] = $this->datasource->client_db_host ?? '';
        $this->state['client_db_port'] = $this->datasource->client_db_port ?? '';
        $this->state['client_db_data'] = $this->datasource->client_db_data ?? '';
        $this->state['client_db_user'] = $this->datasource->client_db_user ?? '';
        // don't show the password by default, require it for changes
        $this->state['client_db_pass'] = '';
        $this->state['client_db_pass_confirmation'] = '';
    }

    public function saveClientConnection(): void
    {
        $this->validate([
            'state.client_db_host' => 'required|string',
            'state.client_db_port' => 'required|numeric',
            'state.client_db_data' => 'required|string',
            'state.client_db_user' => 'required|string',
            'state.client_db_pass' => 'required|confirmed',
        ],
            [
                'client_db_host' => $this->state['client_db_host'],
                'client_db_port' => $this->state['client_db_port'],
                'client_db_data' => $this->state['client_db_data'],
                'client_db_user' => $this->state['client_db_user'],
                'client_db_pass' => $this->state['client_db_pass'],
                'state.client_db_pass_confirmation' => $this->state['client_db_pass_confirmation'],
            ], [
                'state.client_db_host' => 'host server',
                'state.client_db_port' => 'port',
                'state.client_db_data' => 'database',
                'state.client_db_user' => 'username',
                'state.client_db_pass' => 'password and confirmation',
            ]);

        $this->datasource->client_db_host = $this->state['client_db_host'];
        $this->datasource->client_db_port = $this->state['client_db_port'];
        $this->datasource->client_db_data = $this->state['client_db_data'];
        $this->datasource->client_db_user = $this->state['client_db_user'];

        try {
            $this->datasource->client_db_pass = encrypt($this->state['client_db_pass']);
            $this->datasource->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
        }
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.client-db');
    }
}
