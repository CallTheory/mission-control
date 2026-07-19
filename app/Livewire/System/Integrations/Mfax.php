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
            // Automatically create a username / password (the model cast encrypts on write).
            try {
                $this->datasource->mfax_basic_auth_username = bin2hex(random_bytes(8));
                $this->datasource->mfax_basic_auth_password = bin2hex(random_bytes(8));
                $this->datasource->save();
            } catch (Exception $e) {
                throw new Exception('Unable to generate auth user/pass. '.$e->getMessage());
            }
        }

        // Values are decrypted transparently by the model cast.
        $this->state['mfax_basic_auth_username'] = $this->datasource->mfax_basic_auth_username ?? '';
        $this->state['mfax_basic_auth_password'] = $this->datasource->mfax_basic_auth_password ?? '';

        $this->state['mfax_notes'] = $this->datasource->mfax_notes ?? '';
        $this->state['mfax_subject'] = $this->datasource->mfax_subject ?? '';
        $this->state['mfax_sender_name'] = $this->datasource->mfax_sender_name ?? '';

        $this->state['mfax_api_key'] = $this->datasource->mfax_api_key ?? '';

        $this->state['mfax_cover_page_id'] = $this->datasource->mfax_cover_page_id ?? '';
    }

    /**
     * @throws Exception
     */
    public function saveMFaxDetails(): void
    {
        try {
            // First-time setup: enable the provider automatically so a fresh configuration
            // isn't hidden by default. Re-editing existing keys leaves the toggle untouched.
            $wasConfigured = $this->datasource->mfax_api_key !== null;

            $this->datasource->mfax_notes = $this->state['mfax_notes'];
            $this->datasource->mfax_subject = $this->state['mfax_subject'];
            $this->datasource->mfax_api_key = $this->state['mfax_api_key'];
            $this->datasource->mfax_cover_page_id = $this->state['mfax_cover_page_id'];
            $this->datasource->mfax_sender_name = $this->state['mfax_sender_name'];

            if (! $wasConfigured && ! empty($this->state['mfax_api_key'])) {
                $this->datasource->mfax_enabled = true;
            }

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
