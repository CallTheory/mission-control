<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class Intelligent extends Component
{
    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        $this->state['is_db_host'] = $this->datasource->is_db_host ?? '';
        $this->state['is_db_port'] = $this->datasource->is_db_port ?? '';
        $this->state['is_db_data'] = $this->datasource->is_db_data ?? '';
        $this->state['is_db_user'] = $this->datasource->is_db_user ?? '';
        //don't show the password by default, require it for changes
        $this->state['is_db_pass'] = '';
        $this->state['is_db_pass_confirmation'] = '';
    }

    public function saveIntelligentConnection(): void
    {
        $this->validate([
            'state.is_db_host' => 'required|string',
            'state.is_db_port' => 'required|numeric',
            'state.is_db_data' => 'required|string',
            'state.is_db_user' => 'required|string',
            'state.is_db_pass' => 'required|confirmed',
        ],
            [
                'is_db_host' => $this->state['is_db_host'],
                'is_db_port' => $this->state['is_db_port'],
                'is_db_data' => $this->state['is_db_data'],
                'is_db_user' => $this->state['is_db_user'],
                'is_db_pass' => $this->state['is_db_pass'],
                'state.is_db_pass_confirmation' => $this->state['is_db_pass_confirmation'],
            ], [
                'state.is_db_host' => 'host server',
                'state.is_db_port' => 'port',
                'state.is_db_data' => 'database',
                'state.is_db_user' => 'username',
                'state.is_db_pass' => 'password and confirmation',
            ]);

        $this->datasource->is_db_host = $this->state['is_db_host'];
        $this->datasource->is_db_port = $this->state['is_db_port'];
        $this->datasource->is_db_data = $this->state['is_db_data'];
        $this->datasource->is_db_user = $this->state['is_db_user'];

        try {
            $this->datasource->is_db_pass = encrypt($this->state['is_db_pass']);
            $this->datasource->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
        }
    }

    public function render(): View
    {

        return view('livewire.system.data-sources.intelligent');
    }
}
