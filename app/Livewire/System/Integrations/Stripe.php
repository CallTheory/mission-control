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
        // Keys are decrypted transparently by the model cast.
        $this->state['stripe_secret_test_key'] = $this->datasource->stripe_test_secret_key ?? '';
        $this->state['stripe_secret_prod_key'] = $this->datasource->stripe_prod_secret_key ?? '';
    }

    public function saveStripeKeys(): void
    {
        try {
            // The model cast encrypts on write; pass plaintext.
            $this->datasource->stripe_test_secret_key = $this->state['stripe_secret_test_key'];
            $this->datasource->stripe_prod_secret_key = $this->state['stripe_secret_prod_key'];
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
