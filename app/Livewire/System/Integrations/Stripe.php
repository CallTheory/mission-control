<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class Stripe extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use ConfiguresDataSource;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    /**
     * Field names are the column names. The previous form used its own state keys
     * (stripe_secret_test_key for the stripe_test_secret_key column), which was a
     * transposition waiting to be mis-typed.
     */
    protected function settingsFields(): array
    {
        return ['stripe_test_secret_key', 'stripe_prod_secret_key'];
    }

    protected function settingsHeading(): string
    {
        return 'Stripe Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'Secret keys used for card processing. Both are stored encrypted.';
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('stripe_test_secret_key')
                ->label('Test Secret Key')
                ->password()
                ->revealable()
                ->placeholder('sk_test_...')
                ->helperText('Used when card processing runs in test mode.'),

            TextInput::make('stripe_prod_secret_key')
                ->label('Live Secret Key')
                ->password()
                ->revealable()
                ->placeholder('sk_live_...')
                ->helperText('Used for real charges. Keep this secret.'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.integrations.stripe');
    }
}
