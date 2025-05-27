<?php

namespace App\Livewire\System\Integrations;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class Mfax extends Component
{
    public bool $isOpen = false;

    public array $state;

    public DataSource $datasource;

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        if ($this->datasource->mfax_basic_auth_username === null || $this->datasource->mfax_basic_auth_password === null) {
            //Automatically create a username / password
            try {
                $this->datasource->mfax_basic_auth_username = encrypt(bin2hex(random_bytes(8)));
                $this->datasource->mfax_basic_auth_password = encrypt(bin2hex(random_bytes(8)));
                $this->datasource->save();
            } catch (Exception $e) {
                throw new Exception('Unable to generate and encrypt auth user/pass. '.$e->getMessage());
            }
        }

        try {
            $this->state['mfax_basic_auth_username'] = decrypt($this->datasource->mfax_basic_auth_username);
            $this->state['mfax_basic_auth_password'] = decrypt($this->datasource->mfax_basic_auth_password);
        } catch (Exception $e) {
            throw new Exception('Unable to decrypt auth user/pass');
        }

        $this->state['mfax_notes'] = $this->datasource->mfax_notes ?? '';
        $this->state['mfax_subject'] = $this->datasource->mfax_subject ?? '';
        $this->state['mfax_sender_name'] = $this->datasource->mfax_sender_name ?? '';

        if ($this->datasource->mfax_api_key !== null) {
            try {
                $this->state['mfax_api_key'] = decrypt($this->datasource->mfax_api_key);
            } catch (Exception $e) {
                throw new Exception('Unable to decrypt api key');
            }
        } else {
            $this->state['mfax_api_key'] = '';
        }

        $this->state['mfax_cover_page_id'] = $this->datasource->mfax_cover_page_id ?? '';
    }

    /**
     * @throws Exception
     */
    public function saveMFaxDetails(): void
    {
        try {
            $this->datasource->mfax_notes = $this->state['mfax_notes'];
            $this->datasource->mfax_subject = $this->state['mfax_subject'];
            $this->datasource->mfax_api_key = encrypt($this->state['mfax_api_key']);
            $this->datasource->mfax_cover_page_id = $this->state['mfax_cover_page_id'];
            $this->datasource->mfax_sender_name = $this->state['mfax_sender_name'];
            $this->datasource->save();
            $this->dispatch('saved');
            $this->isOpen = false;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.system.integrations.mfax');
    }
}
