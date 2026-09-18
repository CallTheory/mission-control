<?php

declare(strict_types=1);

namespace App\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Jobs\ProcessWctpMessage;
use App\Livewire\Concerns\AuthorizesWctpSection;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use App\Support\WctpSectionAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * The WCTP message log.
 *
 * Gated on wctp.messages rather than wctp.manage, so read access to traffic can be
 * granted to a role that has no business editing carriers or hosts.
 */
class Messages extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesWctpSection;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $host = null;

    protected $queryString = [
        'host' => ['except' => null],
    ];

    /** @var array<string, string> */
    private const DIRECTION_COLORS = ['outbound' => 'info', 'inbound' => 'accent'];

    /** @var array<string, string> */
    private const STATUS_COLORS = [
        'delivered' => 'success', 'sent' => 'info', 'pending' => 'warning',
        'submitted' => 'warning', 'failed' => 'danger', 'undelivered' => 'danger',
    ];

    protected function wctpCapability(): Capability
    {
        return Capability::WctpMessages;
    }

    public function mount()
    {
        $this->authorizeWctpSection();

        if (request()->has('host')) {
            $this->host = request()->get('host');
        }
    }

    /**
     * The host the log is pinned to via ?host=, used for the page caption.
     */
    public function getCurrentHostProperty(): ?EnterpriseHost
    {
        if (! $this->host) {
            return null;
        }

        return EnterpriseHost::whereKey($this->host)->first();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WctpMessage::query()
                ->with('enterpriseHost')
                ->when($this->host, fn ($q) => $q->where('enterprise_host_id', $this->host)))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->description(fn (WctpMessage $record): string => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('enterpriseHost.name')
                    ->label('Host')
                    ->default('Unknown')
                    ->description(fn (WctpMessage $record): ?string => $record->enterpriseHost?->senderID)
                    ->sortable(),

                TextColumn::make('direction')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => self::DIRECTION_COLORS[$state] ?? 'gray'),

                TextColumn::make('to')
                    ->label('From/To')
                    ->state(fn (WctpMessage $record): string => 'To: '.$record->to."\nFrom: ".$record->from)
                    ->searchable(['to', 'from'])
                    ->listWithLineBreaks(),

                TextColumn::make('message')
                    ->limit(50)
                    ->wrap()
                    ->description(fn (WctpMessage $record): string => 'ID: '.$record->wctp_message_id)
                    ->searchable(['message', 'wctp_message_id', 'twilio_sid', 'provider_message_id']),

                TextColumn::make('provider')
                    ->label('Carrier')
                    ->badge()
                    ->color('gray')
                    ->state(fn (WctpMessage $record): string => $record->smsProvider()->label()),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    // The error a failed message came back with, where there is one.
                    ->description(fn (WctpMessage $record): ?string => $record->error_message)
                    ->wrap()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('enterprise_host_id')
                    ->label('Host')
                    ->placeholder('All Hosts')
                    ->options(fn (): array => EnterpriseHost::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('status')
                    ->placeholder('All Statuses')
                    ->options(fn (): array => collect(array_keys(self::STATUS_COLORS))
                        ->mapWithKeys(fn (string $s): array => [$s => ucfirst($s)])
                        ->all()),

                SelectFilter::make('direction')
                    ->placeholder('All Directions')
                    ->options(['outbound' => 'Outbound', 'inbound' => 'Inbound']),

                Filter::make('created_at')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('dateFrom')->label('From'),
                        DatePicker::make('dateTo')->label('To'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dateFrom'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['dateTo'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->link()
                    ->modalHeading('Message Details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    // Read-only, so this is an infolist rather than a form: the entries
                    // read straight off the record and there is nothing to submit.
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('wctp_message_id')->label('Message ID')->fontFamily('mono'),
                            TextEntry::make('carrier_message_uid')->label('Carrier Message ID')->placeholder('N/A')->fontFamily('mono'),
                            TextEntry::make('status')->formatStateUsing(fn (string $state): string => ucfirst($state)),
                            TextEntry::make('provider')
                                ->label('Carrier')
                                ->state(fn (WctpMessage $record): string => $record->smsProvider()->label()),
                            TextEntry::make('provider_message_id')
                                ->label('Carrier Message ID')
                                ->placeholder('N/A')
                                ->fontFamily('mono'),
                            TextEntry::make('twilio_sid')->label('Twilio SID')->placeholder('N/A')->fontFamily('mono'),
                            TextEntry::make('from')->label('From'),
                            TextEntry::make('to')->label('To'),
                            TextEntry::make('message')->columnSpanFull()->prose(),
                            TextEntry::make('error_message')
                                ->label('Error')
                                ->columnSpanFull()
                                ->visible(fn (WctpMessage $record): bool => filled($record->error_message))
                                ->fontFamily('mono'),
                            TextEntry::make('created_at')->label('Created')->dateTime('Y-m-d H:i:s'),
                            TextEntry::make('submitted_at')->label('Submitted')->dateTime('Y-m-d H:i:s')->placeholder('N/A'),
                            TextEntry::make('processed_at')->label('Processed')->dateTime('Y-m-d H:i:s')->placeholder('N/A'),
                            TextEntry::make('delivered_at')->label('Delivered')->dateTime('Y-m-d H:i:s')->placeholder('N/A'),
                        ]),
                    ]),

                Action::make('retry')
                    ->label('Retry')
                    ->link()
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry sending this message?')
                    ->visible(fn (WctpMessage $record): bool => in_array($record->status, ['failed', 'undelivered'], true))
                    ->action(fn (WctpMessage $record) => $this->retryMessage($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([20, 50, 100])
            ->emptyStateHeading('No messages found.');
    }

    public function render()
    {
        $this->authorizeWctpSection();

        return view('livewire.system.wctp.messages');
    }

    public function retryMessage(WctpMessage $message)
    {
        // Retrying puts a message back on the queue, which is a management action
        // rather than a read of the log -- so it needs the stricter capability.
        WctpSectionAccess::authorize(Capability::WctpManage);

        if ($message->status === 'failed') {
            $message->update(['status' => 'pending', 'failed_at' => null]);
            ProcessWctpMessage::dispatch($message);

            Notification::make()
                ->title('Message queued for retry.')
                ->success()
                ->send();
        }
    }
}
