<?php

namespace App\Livewire\Utilities;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesBoardComponent;
use App\Livewire\Concerns\ReviewsBoardCheckItems;
use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\Stats\BoardCheck\Fill as Recent;
use App\Models\System\Settings;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class BoardCheck extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesBoardComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::UtilityBoardCheck;
    }

    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ReviewsBoardCheckItems;

    protected $listeners = ['boardCheckItemUpdated' => 'getRecent'];

    public $currentUrl;

    public function clearRecords(): void
    {
        try {
            BoardCheckItem::truncate();
            BoardCheckActivity::create([
                'activity_type' => 'Cleared all records',
                'user_id' => Auth::user()->id,
            ]);
        } catch (Exception $e) {
        }

        $this->mount();
    }

    public function getRecent(): void
    {
        $settings = Settings::first();
        $configMsgId = config('utilities.board-check.starting_callid');
        $settingsMsgId = $settings->board_check_starting_msgId;
        $lastMsgId = BoardCheckItem::orderBy('msgId', 'desc')->limit(1)->first();

        if (! is_null($settingsMsgId) || ! is_null($lastMsgId->msgId ?? null) || ! is_null($configMsgId)) {
            try {
                $fill = new Recent([
                    'msgId' => $settingsMsgId ?? $lastMsgId->msgId ?? $configMsgId,
                ]);

                $fill->insertBoardCheckItems();
            } catch (Exception $e) {
                if (App::environment('local')) {
                    throw new Exception($e->getMessage());
                }
                Log::alert($e->getMessage());
            }
        }

        $this->mount();
    }

    public function mount(): void
    {
        $this->currentUrl = url()->current();
    }

    protected function acceptOutcome(): array
    {
        return ['label' => 'Confirm Message', 'activity' => 'Dispatcher Approved'];
    }

    protected function escalateOutcome(): array
    {
        return ['label' => 'Escalate to Supervisor', 'activity' => 'Dispatcher Flagged'];
    }

    protected function acceptColumns(): array
    {
        return ['marked_ok', 'approved'];
    }

    protected function escalateColumns(): array
    {
        return ['problem_found'];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardCheckItem::query()
                ->whereNull('approved_at')
                ->whereNull('problem_found_at'))
            ->columns([
                TextColumn::make('msgId')->label('Message ID')->searchable()->sortable(),
                TextColumn::make('callId')->label('Call ID')->searchable()->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color('warning')
                    // Every row in this query is by definition unreviewed.
                    ->state('Needs Reviewed'),
            ])
            ->recordActions([
                $this->reviewAction(),
            ])
            ->defaultSort('msgId')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nothing to do!')
            ->emptyStateDescription('There are no un-checked messages.');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-check');
    }
}
