<?php

namespace App\Livewire\Accounts;

use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Clients\Sources;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Clients extends Component
{
    use WithPagination;

    public string $client_number = '';

    public string $client_name = '';

    public string $billing_code = '';

    public string $account_setting = '';

    public string $account_setting_value = '';

    public string $client_source = '';

    public string $order_by = 'ClientNumber';

    public string $order_direction = 'asc';

    #[Url]
    public int $page;

    #[On('filtered')]
    public function render(): View
    {
        $clientsArray = [];
        $sourcesArray = [];

        try {
            $clients = new Overview([
                'order_by' => $this->order_by,
                'order_direction' => $this->order_direction,
                'client_number' => $this->client_number,
                'client_name' => $this->client_name,
                'billing_code' => $this->billing_code,
                'allowed_accounts' => request()->user()->currentTeam->allowed_accounts,
                'allowed_billing' => request()->user()->currentTeam->allowed_billing,
                'account_setting' => $this->account_setting,
                'account_setting_value' => $this->account_setting_value,
                'client_source' => $this->client_source,
            ]);
            $sources = new Sources(['all' => true]);
            $clientsArray = $clients->results;
            $sourcesArray = $sources->results;
        } catch (Exception $e) {
        }

        $per_page = 50;
        $starting_point = ($this->getPage() * $per_page) - $per_page;

        $clientPaginator = new LengthAwarePaginator(array_slice($clientsArray, $starting_point, $per_page), count($clientsArray), $per_page);

        return view('livewire.accounts.clients', [
            'clients' => $clientPaginator,
            'sources' => $sourcesArray,
        ]);
    }

    public function mount(): void
    {
        $this->client_number = Session::get('client_list:filter:client_number', '');
        $this->client_name = Session::get('client_list:filter:client_name', '');
        $this->billing_code = Session::get('client_list:filter:billing_code', '');
        $this->account_setting = Session::get('client_list:filter:account_setting', '');
        $this->account_setting_value = Session::get('client_list:filter:account_setting_value', '');
        $this->client_source = Session::get('client_list:filter:client_source', '');
        $this->order_by = Session::get('client_list:filter:order_by', 'ClientNumber');
        $this->order_direction = Session::get('client_list:filter:order_direction', 'asc');
    }

    public function orderBy(string $field): void
    {

        if ($this->order_by !== $field) {
            // don't change the direction if we're already sorting by this field
            if (! in_array($field, ['ClientNumber', 'ClientName', 'BillingCode'])) {
                $this->order_by = 'ClientNumber';
            } else {
                $this->order_by = $field;
            }
        } else {
            if ($this->order_direction === 'asc') {
                $this->order_direction = 'desc';
            } else {
                $this->order_direction = 'asc';
            }
        }
    }

    public function applyFilter(): void
    {
        $this->client_name = trim($this->client_name);
        $this->client_number = trim($this->client_number);
        $this->billing_code = trim($this->billing_code);
        $this->account_setting = trim($this->account_setting);
        $this->account_setting = trim($this->account_setting);
        $this->account_setting_value = trim($this->account_setting_value);
        $this->client_source = trim($this->client_source);
        $this->order_by = trim($this->order_by);
        $this->order_direction = trim($this->order_direction);
        Session::put('client_list:filter:client_number', $this->client_number);
        Session::put('client_list:filter:client_name', $this->client_name);
        Session::put('client_list:filter:billing_code', $this->billing_code);
        Session::put('client_list:filter:account_setting', $this->account_setting);
        Session::put('client_list:filter:account_setting_value', $this->account_setting_value);
        Session::put('client_list:filter:client_source', $this->client_source);
        Session::put('client_list:filter:order_by', $this->order_by);
        Session::put('client_list:filter:order_direction', $this->order_direction);
        $this->resetPage();
        $this->dispatch('saved');
        $this->dispatch('filtered');
    }

    public function resetFilter(): void
    {
        Session::put('client_list:filter:client_number', '');
        Session::put('client_list:filter:client_name', '');
        Session::put('client_list:filter:billing_code', '');
        Session::put('client_list:filter:account_setting', '');
        Session::put('client_list:filter:account_setting_value', '');
        Session::put('client_list:filter:client_source', '');
        Session::put('client_list:filter:order_by', 'ClientNumber');
        Session::put('client_list:filter:order_direction', 'asc');
        $this->client_number = '';
        $this->client_name = '';
        $this->billing_code = '';
        $this->account_setting = '';
        $this->account_setting_value = '';
        $this->client_source = '';
        $this->order_by = 'ClientNumber';
        $this->order_direction = 'asc';
        $this->resetPage();
        $this->dispatch('saved');
        $this->dispatch('filtered');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm">
           Loading the account list...one moment, please.
        </div>
        HTML;
    }
}
