<?php

namespace App\Livewire\System\Integrations;

use App\Models\DataSource;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class Stripe extends Component
{
    public bool $isOpen = false;

    public array $state;

    public Datasource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrNew();
        try{
            $this->state['stripe_secret_test_key'] = decrypt($this->datasource->stripe_test_secret_key ?? null);
            $this->state['stripe_secret_prod_key'] = decrypt($this->datasource->stripe_prod_secret_key ?? null);
        }
        catch(Exception $e){
            $this->state['stripe_secret_test_key'] = '';
            $this->state['stripe_secret_prod_key'] = '';
        }
    }

    public function saveStripeKeys(): void
    {
        try {
            $this->datasource->stripe_test_secret_key = encrypt($this->state['stripe_secret_test_key']);
            $this->datasource->stripe_prod_secret_key = encrypt($this->state['stripe_secret_prod_key']);
            $this->datasource->save();
            $this->dispatch('saved');
            $this->isOpen = false;
        } catch (Exception $e) {
        }
    }

    public function render(): View
    {
        return view('livewire.system.integrations.stripe');
    }
}
