<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use App\Models\RingCentralSender;
use App\Services\Faxing\RingCentralClient;
use App\Services\Faxing\RingCentralExtension;
use App\Services\Faxing\RingCentralRateLimitState;
use App\Services\Faxing\RingCentralSenderPool;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Additional RingCentral extensions to send faxes through.
 *
 * RingCentral's fax rate limit is per authenticated extension per app, so every extension
 * added here brings its own allowance and the pool's throughput grows with it. Each one
 * needs its own licensed user and its own JWT — a token for the primary extension cannot
 * send "as" another extension in a way that counts against that extension's limit.
 *
 * Gated on system.integrations rather than system.access because a JWT is a credential,
 * the same as the primary one under System → Integrations.
 */
class RingCentralSendingExtensions extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => RingCentralSender::query())
            ->defaultSort('sort')
            ->emptyStateHeading('No additional sending extensions')
            ->emptyStateDescription('Every fax goes out through the primary extension, as it always has.')
            ->columns([
                TextColumn::make('label'),

                TextColumn::make('extension_id')->label('Extension ID')->fontFamily('mono')->placeholder('Unknown'),

                TextColumn::make('rate_limit')
                    ->label('Rate limit')
                    ->state(fn (RingCentralSender $record): string => $this->describeState($record->senderKey())),

                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                Action::make('createSender')
                    ->label('Add Extension')
                    ->modalHeading('Add Sending Extension')
                    ->schema($this->senderSchema(requireJwt: true))
                    ->action(function (array $data, Action $action): void {
                        Gate::authorize(Capability::SystemIntegrations->value);

                        $extensionId = $this->verifiedExtensionId($data['jwt_token'], null, $action);

                        RingCentralSender::create([
                            'label' => $data['label'],
                            'jwt_token' => $data['jwt_token'],
                            'extension_id' => $extensionId,
                            'enabled' => (bool) ($data['enabled'] ?? true),
                            'sort' => (int) ($data['sort'] ?? 0),
                        ]);

                        Notification::make()->title('Sending extension added.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editSender')
                    ->label('Edit')
                    ->link()
                    ->modalHeading('Edit Sending Extension')
                    ->fillForm(fn (RingCentralSender $record): array => [
                        'label' => $record->label,
                        'enabled' => $record->enabled,
                        'sort' => $record->sort,
                    ])
                    ->schema($this->senderSchema(requireJwt: false))
                    ->action(function (RingCentralSender $record, array $data, Action $action): void {
                        Gate::authorize(Capability::SystemIntegrations->value);

                        $update = [
                            'label' => $data['label'],
                            'enabled' => (bool) ($data['enabled'] ?? true),
                            'sort' => (int) ($data['sort'] ?? 0),
                        ];

                        // A blank JWT keeps the stored one, as on the primary's settings.
                        if (filled($data['jwt_token'] ?? null)) {
                            $update['jwt_token'] = $data['jwt_token'];
                            $update['extension_id'] = $this->verifiedExtensionId($data['jwt_token'], $record, $action);
                        }

                        $record->update($update);

                        Notification::make()->title('Sending extension updated.')->success()->send();
                    }),

                Action::make('deleteSender')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Faxes already sent through this extension can no longer be polled or resent from Mission Control once it is deleted. Disable it instead to stop sending through it.')
                    ->action(function (RingCentralSender $record): void {
                        Gate::authorize(Capability::SystemIntegrations->value);

                        app(RingCentralRateLimitState::class)->forget($record->senderKey());
                        $record->delete();

                        Notification::make()->title('Sending extension removed.')->success()->send();
                    }),
            ]);
    }

    /**
     * The primary extension's live rate-limit state, shown above the table.
     */
    public function primaryState(): string
    {
        return $this->describeState(RingCentralExtension::PRIMARY);
    }

    /**
     * What RingCentral last reported for an extension's fax sends.
     */
    private function describeState(string $key): string
    {
        $state = app(RingCentralRateLimitState::class)->snapshot($key);
        $now = now()->getTimestamp();

        if (($state['locked_until'] ?? 0) > $now) {
            return 'Locked out for '.($state['locked_until'] - $now).'s';
        }

        if (! isset($state['remaining'])) {
            return 'No recent sends';
        }

        $limit = $state['limit'] ?? null;
        $window = $state['window'] ?? null;

        return $state['remaining'].($limit !== null ? " of {$limit}" : '').' left'
            .($window !== null ? " per {$window}s" : '')
            .' · '.now()->setTimestamp((int) $state['observed_at'])->diffForHumans();
    }

    /**
     * Authenticate with the JWT and return the extension it belongs to.
     *
     * Saving a JWT that does not work would only surface later as failed faxes. And a JWT
     * for an extension already in the pool adds no capacity — the limit is per extension,
     * not per token — so it is refused rather than quietly doubling up.
     */
    private function verifiedExtensionId(string $jwt, ?RingCentralSender $editing, Action $action): ?string
    {
        $datasource = DataSource::first();

        if ($datasource === null || blank($datasource->ringcentral_client_id)) {
            Notification::make()->title('Configure RingCentral under System → Integrations first.')->danger()->send();
            $action->halt();
        }

        // halt() throws, but is not declared as never-returning.
        $extensionId = null;

        try {
            $client = new RingCentralClient($datasource, $jwt);
            $client->platform();
            $extensionId = $client->extensionId();
        } catch (Throwable $e) {
            Notification::make()
                ->title('RingCentral did not accept this JWT.')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            $action->halt();
        }

        if ($extensionId !== null && in_array($extensionId, $this->extensionIdsInUse($datasource, $editing), true)) {
            Notification::make()
                ->title('That JWT belongs to an extension already sending faxes.')
                ->body('RingCentral counts the limit per extension, so a second token for the same extension adds no capacity. Use a JWT for a different user.')
                ->danger()
                ->persistent()
                ->send();
            $action->halt();
        }

        return $extensionId;
    }

    /**
     * @return list<string>
     */
    private function extensionIdsInUse(DataSource $datasource, ?RingCentralSender $editing): array
    {
        $ids = RingCentralSender::query()
            ->when($editing !== null, fn ($query) => $query->whereKeyNot($editing->id))
            ->whereNotNull('extension_id')
            ->pluck('extension_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        try {
            $primary = app(RingCentralSenderPool::class)->find($datasource, RingCentralExtension::PRIMARY);

            if ($primary !== null) {
                $client = RingCentralClient::forExtension($datasource, $primary);
                $client->platform();

                if ($primaryId = $client->extensionId()) {
                    $ids[] = $primaryId;
                }
            }
        } catch (Throwable) {
            // The primary being unreachable must not block adding a sender.
        }

        return array_values($ids);
    }

    /**
     * @return array<int, mixed>
     */
    private function senderSchema(bool $requireJwt): array
    {
        return [
            TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->helperText('Which user or extension this is, e.g. "Fax 2 (ext. 102)".'),

            TextInput::make('jwt_token')
                ->label('JWT')
                ->password()
                ->revealable()
                ->required($requireJwt)
                ->helperText(($requireJwt ? '' : 'Leave blank to keep the stored JWT. ')
                    .'A JWT credential created by this user for the same RingCentral app as the primary. '
                    .'Faxes sent through it show the fax number set in this extension\'s outbound fax settings.'),

            TextInput::make('sort')
                ->numeric()
                ->default(0)
                ->helperText('Display order only; faxes rotate across every enabled extension.'),

            Toggle::make('enabled')->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.ringcentral-sending-extensions');
    }
}
