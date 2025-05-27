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

    public DataSource $datasource;

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();

        if ($this->datasource->ringcentral_client_secret !== null && $this->datasource->ringcentral_jwt_token !== null) {
            try {
                $this->state['ringcentral_jwt_token'] = decrypt($this->datasource->ringcentral_jwt_token);
                $this->state['ringcentral_client_secret'] = decrypt($this->datasource->ringcentral_client_secret);

            } catch (Exception $e) {
                throw new Exception('Unable to decrypt Client Secret or JWT token');
            }
        } else {
            $this->state['ringcentral_jwt_token'] = '';
            $this->state['ringcentral_client_secret'] = '';
        }

        $this->state['ringcentral_client_id'] = $this->datasource->ringcentral_client_id ?? '';
        $this->state['ringcentral_api_endpoint'] = $this->datasource->ringcentral_api_endpoint ?? '';
    }

    /**
     * @throws Exception
     */
    public function saveRingCentralFaxDetails(): void
    {
        try {
            $this->datasource->ringcentral_client_id = $this->state['ringcentral_client_id'];
            $this->datasource->ringcentral_client_secret = encrypt($this->state['ringcentral_client_secret']);
            $this->datasource->ringcentral_jwt_token = encrypt($this->state['ringcentral_jwt_token']);
            $this->datasource->ringcentral_api_endpoint = $this->state['ringcentral_api_endpoint'];
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
