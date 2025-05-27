<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class IsUser extends Component
{
    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        $this->state['is_username'] = $this->datasource->is_agent_username ?? '';
        $this->state['is_password'] = '';
        $this->state['is_password_confirmation'] = '';
    }

    public function saveIntelligentUser(): void
    {
        $this->validate([
            'state.is_username' => 'required',
            'state.is_password' => 'required|confirmed',
        ], [
            'state.is_username' => $this->state['is_username'],
            'state.is_password' => $this->state['is_password'],
        ]);

        $this->datasource->is_agent_username = $this->state['is_username'];
        $this->datasource->is_agent_password = encrypt($this->state['is_password']);
        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.is-user');
    }
}
