<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\SmsProvider;
use App\Models\EnterpriseHost;
use App\Services\Sms\SmsGatewayManager;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The phone-number half of the enterprise host forms, shared by the System and
 * Utilities screens so the two cannot drift.
 *
 * A number is paired with the carrier that owns it, because a DID belongs to exactly
 * one carrier and one host can hold numbers from several. Storage stays split: the
 * `phone_numbers` list is the authoritative set of numbers (unchanged, and still
 * what inbound routing reads), and `number_providers` maps the normalised number to
 * its carrier. Leaving a row's carrier blank means "use the system default", which
 * is also what every number did before carriers were selectable.
 */
trait ManagesHostPhoneNumbers
{
    /**
     * The carrier a number with none assigned will actually use, for labelling.
     */
    protected function defaultCarrierLabel(): string
    {
        return app(SmsGatewayManager::class)->defaultProvider()->label();
    }

    /**
     * The repeater shown in the create/edit dialogs.
     */
    protected function phoneNumbersField(): Repeater
    {
        $default = app(SmsGatewayManager::class)->defaultProvider();

        return Repeater::make('phone_numbers')
            ->label('Phone Numbers')
            ->addActionLabel('Add phone number')
            ->helperText('Entered numbers are normalised to E.164. The carrier that owns a number is the one its messages are sent through.')
            ->table([
                Repeater\TableColumn::make('Number'),
                Repeater\TableColumn::make('Carrier'),
            ])
            ->schema([
                TextInput::make('number')
                    ->placeholder('+15551234567')
                    ->required()
                    ->rules(['string', 'regex:/^[\+]?[1-9]\d{1,14}$/']),

                Select::make('provider')
                    ->options(SmsProvider::options())
                    ->placeholder('System default ('.$default->label().')')
                    ->native(false),
            ])
            ->defaultItems(0);
    }

    /**
     * Repeater rows for a host, pairing each stored number with its carrier.
     *
     * @return array<int, array{number: string, provider: string|null}>
     */
    protected function phoneNumberRows(EnterpriseHost $record): array
    {
        return array_map(fn (string $number): array => [
            'number' => $number,
            'provider' => $record->providerForNumber($number)?->value,
        ], array_values($record->phone_numbers ?? []));
    }

    /**
     * Turn submitted repeater rows back into the two columns, normalising numbers
     * and dropping duplicates (the last carrier chosen for a number wins).
     *
     * @param  array<mixed>  $rows
     * @return array{phone_numbers: array<int, string>, number_providers: array<string, string>}
     */
    protected function phoneNumberAttributes(array $rows): array
    {
        $numbers = [];
        $providers = [];

        foreach ($rows as $row) {
            // Tolerates the plain-string shape too, so a host saved by an older
            // form -- or a seeder -- round-trips unchanged.
            $number = is_array($row) ? ($row['number'] ?? null) : $row;

            if (blank($number)) {
                continue;
            }

            $normalized = EnterpriseHost::normalizeNumber((string) $number);

            if ($normalized === '') {
                continue;
            }

            $numbers[$normalized] = '+'.$normalized;

            $provider = is_array($row) ? SmsProvider::tryFromKey($row['provider'] ?? null) : null;

            if ($provider !== null) {
                $providers[$normalized] = $provider->value;
            } else {
                // Cleared on this save: fall back to the system default again.
                unset($providers[$normalized]);
            }
        }

        return [
            'phone_numbers' => array_values($numbers),
            'number_providers' => $providers,
        ];
    }
}
