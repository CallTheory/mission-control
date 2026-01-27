<?php

namespace App\Livewire\System\DataSources;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class AmtelcoSMTP extends Component
{
    public array $state;

    public DataSource $datasource;

    public string $connectionStatus = '';

    public string $connectionMessage = '';

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

    public function testConnection(): void
    {
        $host = $this->state['amtelco_inbound_smtp_host'] ?: $this->datasource->amtelco_inbound_smtp_host;
        $port = $this->state['amtelco_inbound_smtp_port'] ?: $this->datasource->amtelco_inbound_smtp_port;

        if (empty($host) || empty($port)) {
            $this->connectionStatus = 'failed';
            $this->connectionMessage = 'Please fill in host and port fields.';

            return;
        }

        try {
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 5);

            if (! $socket) {
                $this->connectionStatus = 'failed';
                $this->connectionMessage = "Connection failed: $errstr ($errno)";

                return;
            }

            stream_set_timeout($socket, 5);

            $banner = fgets($socket, 512);

            if (! $banner || ! str_starts_with($banner, '220')) {
                fclose($socket);
                $this->connectionStatus = 'failed';
                $this->connectionMessage = 'Invalid SMTP response: '.trim($banner ?: 'No response');

                return;
            }

            fwrite($socket, "EHLO test\r\n");
            $response = fgets($socket, 512);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            $this->connectionStatus = 'success';
            $this->connectionMessage = 'SMTP connection successful! Banner: '.trim($banner);
        } catch (Exception $e) {
            $this->connectionStatus = 'failed';
            $this->connectionMessage = 'Connection failed: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.amtelco-smtp');
    }
}
