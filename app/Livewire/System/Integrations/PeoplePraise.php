<?php

namespace App\Livewire\System\Integrations;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class PeoplePraise extends Component
{
    public bool $isOpen = false;

    public array $state;

    public DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        try {
            $this->state['people_praise_basic_auth_user'] = decrypt($this->datasource->people_praise_basic_auth_user);
            $this->state['people_praise_basic_auth_pass'] = decrypt($this->datasource->people_praise_basic_auth_pass);
        } catch (Exception $e) {
            $this->state['people_praise_basic_auth_user'] = '';
            $this->state['people_praise_basic_auth_pass'] = '';
        }
    }

    /**
     * @throws Exception
     */
    public function savePeoplePraiseDetails(): void
    {
        try {
            $this->datasource->people_praise_basic_auth_user = encrypt($this->state['people_praise_basic_auth_user']);
            $this->datasource->people_praise_basic_auth_pass = encrypt($this->state['people_praise_basic_auth_pass']);
            $this->datasource->save();
            $this->dispatch('saved');
            $this->isOpen = false;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.system.integrations.people-praise');
    }
}
