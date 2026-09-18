<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Clients\Sources;
use App\Support\Tables\StatRecords;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;

class Clients extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * Per-account switches the listing can filter on, as offered by the Amtelco
     * client record.
     *
     * @var array<string, string>
     */
    public const ACCOUNT_SETTINGS = [
        'SaveDiscardedMessages' => 'Save Discarded Messages',
        'CheckinPending' => 'Checkin Pending',
        'LogVoice' => 'Log Voice',
        'PerfectAnswer' => 'Perfect Answer',
        'AutoConnect' => 'Auto-Connect (Deprecated?)',
        'Emergency' => 'Emergency',
        'DoneKeyCancelsScript' => 'Done Key Cancels Script',
        'HangupRemovesWorkArea' => 'Hangup Removes Work Area',
        'TransferConfRemovesWorkArea' => 'Transfer Conference Removes Work Area',
        'PciCompliance' => 'PCI Compliance',
        'OverrideOpLimit' => 'Override Op Limit',
        'AnnounceATTA' => 'Announce ATTA',
        'RepeatATTA' => 'Repeat ATTA',
        'AnnounceCallsInQue' => 'Announce Calls In Queue',
        'DontStartScriptOnFetch' => 'Dont Start Script On Fetch',
        'ScreenCapture' => 'Screen Capture',
        'SelectNextUndelMsgWhenDel' => 'Select Next Undelivered Msg When Deleted',
        'PlayQualityPrompt' => 'Play Quality Prompt',
        'LoggerBeep' => 'Logger Beep',
        'SpecialOldToNew' => 'Special Old To New',
        'SaveEditedSpecial' => 'Save Edited Special',
        'ShowSpecials' => 'Show Specials',
        'ShowInfos' => 'Show Infos',
        'DirectCheckin' => 'Direct Checkin',
        'VoiceMailPlayBeep' => 'Voice Mail Play Beep',
        'VoiceMailOldToNew' => 'Voice Mail Old To New',
        'VoiceMailRevert' => 'Voice Mail Revert',
        'VmChgPasscode' => 'Vm Change Passcode',
        'VmChgGreeting' => 'Vm Change Greeting',
        'VoiceMailPrivate' => 'Voice Mail Private',
        'VoiceMailANI' => 'Voice Mail ANI',
        'SecureVMTransfer' => 'Secure VM Transfer',
        'NewVMRunsMergecomm' => 'New VM Runs Mergecomm',
        'ExcludeFromSurvey' => 'Exclude From Survey',
        'LogWhenMessageViewed' => 'Log When Message Viewed',
        'UseOrgClientForDIDLimit' => 'Use Original Client For DID Limit',
        'ExemptFromSystemHoliday' => 'Exempt From System Holiday',
        'RecordPatch' => 'Record Patch',
        'NoLoggerDialout' => 'No Logger Dialout',
        'UseCallersCallerIdOnDialouts' => 'Use Callers CallerId On Dialouts',
        'Voci' => 'Voci',
        'Inactive' => 'Inactive',
        'PresentAbandon' => 'Present Abandon',
        'ExemptFromSystemEmergency' => 'Exempt From System Emergency',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?string $search, array $filters) {
                $rows = $this->clientRows($filters, $search, $sortColumn, $sortDirection);

                // The T-SQL already applied the ORDER BY, so only page here.
                return StatRecords::paginate(
                    rows: $rows,
                    page: $page,
                    perPage: $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('ClientNumber')->label('Client Number')->sortable(),
                TextColumn::make('BillingCode')->label('Billing Code')->sortable(),

                TextColumn::make('ClientName')
                    ->label('Client Name')
                    ->sortable()
                    ->url(fn (array $record): string => '/accounts/client/'.$record['ClientNumber']),

                TextColumn::make('Sources')
                    ->label('Sources')
                    ->badge()
                    ->state(fn (array $record): array => $this->sourcesByClient()[$record['cltId']] ?? []),
            ])
            ->filters([
                Filter::make('account')
                    ->schema([
                        TextInput::make('client_number')->label('Client Number'),
                        TextInput::make('billing_code')->label('Billing Code'),
                        TextInput::make('client_name')->label('Client Name'),
                        TextInput::make('client_source')->label('Source'),
                        Select::make('account_setting')
                            ->label('Account Setting')
                            ->options(self::ACCOUNT_SETTINGS)
                            ->searchable(),
                        Select::make('account_setting_value')
                            ->label('Setting Value')
                            ->options(['0' => 'Off', '1' => 'On']),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    // Filtering happens inside the T-SQL, not over the returned rows,
                    // so there is nothing to apply to a query builder here.
                    ->query(fn ($query) => $query),
            ], layout: FiltersLayout::AboveContent)
            // One filter group, so it gets the whole width. Filament's default grid
            // for AboveContent is 2-5 columns depending on breakpoint, which would
            // squeeze this entire group -- and the grid of fields inside it -- into a
            // single narrow column on a wide screen.
            ->filtersFormColumns(1)
            ->searchable()
            // Replaces the hand-rolled Session::put wiring the filter form used to do.
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->defaultSort('ClientNumber')
            ->paginated([50, 100, 200])
            ->emptyStateHeading('No records found.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, object>
     */
    private function clientRows(array $filters, ?string $search, ?string $sortColumn, ?string $sortDirection): array
    {
        $account = $filters['account'] ?? [];
        $team = request()->user()->currentTeam;

        try {
            return (new Overview([
                // Overview allow-lists both of these before interpolating them into
                // its ORDER BY, so an unexpected column falls back rather than injecting.
                'order_by' => $sortColumn ?? 'ClientNumber',
                'order_direction' => $sortDirection ?? 'asc',
                // A table-wide search is the same thing as a client-name contains filter
                // as far as the underlying query is concerned.
                'client_name' => (string) ($account['client_name'] ?? '') ?: (string) $search,
                'client_number' => (string) ($account['client_number'] ?? ''),
                'billing_code' => (string) ($account['billing_code'] ?? ''),
                'client_source' => (string) ($account['client_source'] ?? ''),
                'account_setting' => (string) ($account['account_setting'] ?? ''),
                'account_setting_value' => (string) ($account['account_setting_value'] ?? ''),
                'allowed_accounts' => $team->allowed_accounts,
                'allowed_billing' => $team->allowed_billing,
            ]))->results;
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @return array<int|string, array<int, string>>
     */
    private function sourcesByClient(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            $rows = (new Sources(['all' => true]))->results;
        } catch (Exception) {
            return $cache = [];
        }

        $grouped = [];

        foreach ($rows as $source) {
            $grouped[$source->cltId][] = $source->Source;
        }

        return $cache = $grouped;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm">
           Loading the account list...one moment, please.
        </div>
        HTML;
    }

    public function render(): View
    {
        return view('livewire.accounts.clients');
    }
}
