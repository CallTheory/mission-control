<?php

namespace App\Livewire\Dashboard;

use App\Models\DataSource;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Illuminate\View\View;
use Livewire\Component;
use stdClass;

class Status extends Component
{
    public array $monitored;

    public function mount(): void
    {
        $services = DataSource::firstOrFail();

        $this->monitored['customer_landing_site'] = new stdClass;
        $this->monitored['customer_landing_site']->name = 'Marketing Site';
        $this->monitored['customer_landing_site']->status = 'unmonitored'; // up/partial/down

        $this->monitored['amtelco_intelligent_api'] = new stdClass;
        $this->monitored['amtelco_intelligent_api']->name = 'Intelligent Series Web REST API';
        $this->monitored['amtelco_intelligent_api']->status = 'unmonitored'; // up/partial/down

        $this->monitored['amtelco_intelligent_web'] = new stdClass;
        $this->monitored['amtelco_intelligent_web']->name = 'Intelligent Series miTeamWeb';
        $this->monitored['amtelco_intelligent_web']->status = 'unmonitored'; // up/partial/down

        $this->monitored['amtelco_intelligent_service'] = new stdClass;
        $this->monitored['amtelco_intelligent_service']->name = 'Intelligent Series Service (Coming Soon)';
        $this->monitored['amtelco_intelligent_service']->status = 'unmonitored'; // up/partial/down

        $this->monitored['amtelco_genesis_status'] = new stdClass;
        $this->monitored['amtelco_genesis_status']->name = 'Genesis Telephony (Coming Soon)';
        $this->monitored['amtelco_genesis_status']->status = 'unmonitored'; // up/partial/down

        if ($services) {
            $this->monitored['amtelco_intelligent_web'] = new stdClass;
            $this->monitored['amtelco_intelligent_web']->name = 'Intelligent Series miTeamWeb';

            if ($services->miteamweb_site) {
                $client = new Guzzle();
                try {
                    $response = $client->get($services->miteamweb_site);
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        $this->monitored['amtelco_intelligent_web']->status = 'up'; // up/partial/down
                    } elseif ($statusCode >= 400 && $statusCode < 500) {
                        $this->monitored['amtelco_intelligent_web']->status = 'partial'; // up/partial/down
                    } else {
                        $this->monitored['amtelco_intelligent_web']->status = 'down'; // up/partial/down
                    }
                } catch (Exception $e) {
                    $this->monitored['amtelco_intelligent_web']->status = 'down'; // up/partial/down
                }
            } else {
                $this->monitored['amtelco_intelligent_web']->status = 'unmonitored'; // up/partial/down
            }

            $this->monitored['amtelco_intelligent_api'] = new stdClass;
            $this->monitored['amtelco_intelligent_api']->name = 'Intelligent Series Web REST API';

            if ($services->is_web_api_endpoint) {
                $client = new Guzzle();
                try {
                    $response = $client->get($services->is_web_api_endpoint);
                    $statusCode = $response->getStatusCode();
                } catch (Exception $e) {
                    $this->monitored['amtelco_intelligent_api']->status = 'down'; // up/partial/down
                }

                if ($statusCode === 200) {
                    $this->monitored['amtelco_intelligent_api']->status = 'up'; // up/partial/down
                } elseif ($statusCode >= 400 && $statusCode < 500) {
                    $this->monitored['amtelco_intelligent_api']->status = 'partial'; // up/partial/down
                } else {
                    $this->monitored['amtelco_intelligent_api']->status = 'down'; // up/partial/down
                }
            } else {
                $this->monitored['amtelco_intelligent_api']->status = 'unmonitored'; // up/partial/down
            }

            $this->monitored['customer_landing_site'] = new stdClass;
            $this->monitored['customer_landing_site']->name = 'Marketing Site';

            if ($services->marketing_site) {
                $client = new Guzzle();
                try {
                    $response = $client->get($services->marketing_site);
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        $this->monitored['customer_landing_site']->status = 'up'; // up/partial/down
                    } elseif ($statusCode >= 400 && $statusCode < 500) {
                        $this->monitored['customer_landing_site']->status = 'partial'; // up/partial/down
                    } else {
                        $this->monitored['customer_landing_site']->status = 'down'; // up/partial/down
                    }
                } catch (Exception $e) {
                    $this->monitored['customer_landing_site']->status = 'down'; // up/partial/down
                }
            } else {
                $this->monitored['customer_landing_site']->status = 'unmonitored'; // up/partial/down
            }

            $this->monitored['amtelco_intelligent_service'] = new stdClass;
            $this->monitored['amtelco_intelligent_service']->name = 'Intelligent Series Service (Coming Soon)';
            $this->monitored['amtelco_intelligent_service']->status = 'unmonitored'; // up/partial/down

            $this->monitored['amtelco_genesis_status'] = new stdClass;
            $this->monitored['amtelco_genesis_status']->name = 'Genesis Telephony (Coming Soon)';
            $this->monitored['amtelco_genesis_status']->status = 'unmonitored'; // up/partial/down
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.status');
    }
}
