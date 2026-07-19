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

        // Values are decrypted transparently by the model cast.
        $this->state['people_praise_basic_auth_user'] = $this->datasource->people_praise_basic_auth_user ?? '';
        $this->state['people_praise_basic_auth_pass'] = $this->datasource->people_praise_basic_auth_pass ?? '';
    }

    /**
     * @throws Exception
     */
    public function savePeoplePraiseDetails(): void
    {
        try {
            // The model cast encrypts on write; pass plaintext.
            $this->datasource->people_praise_basic_auth_user = $this->state['people_praise_basic_auth_user'];
            $this->datasource->people_praise_basic_auth_pass = $this->state['people_praise_basic_auth_pass'];
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
