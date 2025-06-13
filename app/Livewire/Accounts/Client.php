<?php

namespace App\Livewire\Accounts;

use App\Models\Stats\Clients\Client as IntelligentClient;
use App\Models\Stats\Clients\Greetings;
use App\Models\Stats\Clients\Sources;
use App\Models\System\Settings;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class Client extends Component
{
    public string $switch_timezone = 'UTC';

    public string $client_number;

    public function mount(string $client_number): void
    {

        $settings = Settings::first();
        $this->switch_timezone = $settings->switch_data_timezone ?? 'UTC';
        $this->client_number = $client_number;
    }

    /**
     * @throws Exception
     */
    public function render(): View
    {
        $account = new IntelligentClient(['client_number' => $this->client_number]);
        $sources = new Sources(['cltId' => $account->cltId]);
        $greetings = new Greetings(['cltId' => $account->cltId]);

        return view('livewire.accounts.client',
            [
                'account' => $account->details(),
                'sources' => $sources->results,
                'greetings' => $greetings->results]
        );
    }
}
