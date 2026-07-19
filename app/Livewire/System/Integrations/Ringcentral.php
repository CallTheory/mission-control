<?php

namespace App\Livewire\System\Integrations;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class Ringcentral extends Component
{
    public bool $isOpen = false;

    public array $state;

    // Whether a secret is already stored (so the view can show "leave blank to keep
    // current") without ever shipping the decrypted value to the browser.
    public bool $hasClientSecret = false;

    public bool $hasJwtToken = false;

    public DataSource $datasource;

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        // Never place decrypted secrets into public Livewire state: public props are
        // serialized into the component snapshot sent to (and echoed back from) the
        // browser. The secret fields stay blank when editing; a blank value on save
        // preserves the stored secret (see saveRingCentralFaxDetails).
        $this->state['ringcentral_jwt_token'] = '';
        $this->state['ringcentral_client_secret'] = '';
        $this->hasClientSecret = $this->datasource->ringcentral_client_secret !== null;
        $this->hasJwtToken = $this->datasource->ringcentral_jwt_token !== null;

        $this->state['ringcentral_client_id'] = $this->datasource->ringcentral_client_id ?? '';
        $this->state['ringcentral_api_endpoint'] = $this->datasource->ringcentral_api_endpoint ?? '';
    }

    /**
     * @throws Exception
     */
    public function saveRingCentralFaxDetails(): void
    {
        try {
            // First-time setup: enable the provider automatically so a fresh configuration
            // isn't hidden by default. Re-editing existing keys leaves the toggle untouched.
            $wasConfigured = $this->datasource->ringcentral_client_id !== null;

            $this->datasource->ringcentral_client_id = $this->state['ringcentral_client_id'];
            // Only overwrite a secret when the admin actually entered a new value;
            // a blank field keeps the existing stored (encrypted) secret.
            // The model cast encrypts on write; pass plaintext.
            if (! empty($this->state['ringcentral_client_secret'])) {
                $this->datasource->ringcentral_client_secret = $this->state['ringcentral_client_secret'];
            }
            if (! empty($this->state['ringcentral_jwt_token'])) {
                $this->datasource->ringcentral_jwt_token = $this->state['ringcentral_jwt_token'];
            }
            $this->datasource->ringcentral_api_endpoint = $this->state['ringcentral_api_endpoint'];

            if (! $wasConfigured && ! empty($this->state['ringcentral_client_id'])) {
                $this->datasource->ringcentral_enabled = true;
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
        return view('livewire.system.integrations.ringcentral');
    }
}
