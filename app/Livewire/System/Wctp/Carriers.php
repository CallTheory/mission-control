<?php

declare(strict_types=1);

namespace App\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Enums\SmsProvider;
use App\Livewire\Concerns\AuthorizesWctpSection;
use App\Models\DataSource;
use App\Services\Sms\SmsGatewayManager;
use App\Support\SmsWebhookUrls;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The carriers page: which of the three can send, the URLs to paste into each
 * portal, and which carrier a number with none assigned falls back to.
 */
class Carriers extends Component implements HasActions, HasSchemas
{
    use AuthorizesWctpSection;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function wctpCapability(): Capability
    {
        return Capability::WctpManage;
    }

    public function mount(): void
    {
        $this->authorizeWctpSection();
    }

    /**
     * Every carrier the gateway can relay through, with what an operator needs to
     * finish setting it up: whether it can send, the number it sends from, and the
     * URLs to paste into its portal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function carriers(): array
    {
        $gateways = app(SmsGatewayManager::class);
        $default = $gateways->defaultProvider();

        return array_map(fn (SmsProvider $provider): array => [
            'key' => $provider->value,
            'label' => $provider->label(),
            'configured' => $gateways->gateway($provider)->isConfigured(),
            'from' => $gateways->gateway($provider)->fromNumber(),
            'is_default' => $provider === $default,
            'inbound_url' => SmsWebhookUrls::inbound($provider),
            'status_url' => SmsWebhookUrls::status($provider),
        ], SmsProvider::cases());
    }

    /**
     * The carrier used by any number that has not been assigned one of its own.
     */
    public function defaultProvider(): SmsProvider
    {
        return app(SmsGatewayManager::class)->defaultProvider();
    }

    /**
     * Choosing the fallback carrier. Numbers assigned to a carrier are unaffected --
     * this only decides where a number with no carrier goes.
     */
    public function defaultProviderAction(): Action
    {
        return Action::make('defaultProvider')
            ->label('Default carrier')
            ->modalHeading('Default SMS carrier')
            ->modalDescription('Used for outbound messages from any number that has not been assigned a carrier of its own.')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (): array => ['sms_default_provider' => $this->defaultProvider()->value])
            ->schema([
                Select::make('sms_default_provider')
                    ->label('Carrier')
                    ->options(SmsProvider::options())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $this->authorizeWctpSection();

                $provider = SmsProvider::tryFromKey($data['sms_default_provider'] ?? null);

                if ($provider === null) {
                    Notification::make()->title('Unknown carrier.')->danger()->send();

                    return;
                }

                $dataSource = DataSource::firstOrNew();
                $dataSource->sms_default_provider = $provider->value;
                $dataSource->save();

                Notification::make()->title("Default carrier set to {$provider->label()}.")->success()->send();
            });
    }

    public function render(): View
    {
        $this->authorizeWctpSection();

        return view('livewire.system.wctp.carriers');
    }
}
