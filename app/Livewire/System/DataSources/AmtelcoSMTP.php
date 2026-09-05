<?php

declare(strict_types=1);

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsDataSourceSettings;
use App\Models\DataSource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class AmtelcoSMTP extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use EditsDataSourceSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public string $connectionStatus = '';

    public string $connectionMessage = '';

    protected function requiredCapability(): Capability
    {
        return Capability::SystemDataSources;
    }

    protected function settingsFields(): array
    {
        return ['amtelco_inbound_smtp_host', 'amtelco_inbound_smtp_port'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('amtelco_inbound_smtp_host')
                ->label('Inbound SMTP Host')
                ->required()
                ->validationAttribute('inbound SMTP host'),

            TextInput::make('amtelco_inbound_smtp_port')
                ->label('Inbound SMTP Port')
                ->numeric()
                ->required()
                ->validationAttribute('inbound SMTP port'),
        ];
    }

    public function testConnection(): void
    {
        $host = $this->data['amtelco_inbound_smtp_host'] ?: DataSource::firstOrNew()->amtelco_inbound_smtp_host;
        $port = $this->data['amtelco_inbound_smtp_port'] ?: DataSource::firstOrNew()->amtelco_inbound_smtp_port;

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
