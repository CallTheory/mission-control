<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class BoardActivity extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public string|int $msgId;

    public string|int $user_id;

    public $currentUrl;

    public function mount(?int $msgId = null, ?int $user_id = null): void
    {
        $this->currentUrl = url()->current();

        if ($msgId) {
            $this->msgId = $msgId;
        }

        if ($user_id) {
            $this->user_id = $user_id;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            // with('user') replaces a User::find() that ran once per rendered row.
            ->query(fn (): Builder => BoardCheckActivity::query()
                ->with('user')
                ->when(isset($this->msgId) && (string) $this->msgId !== '',
                    fn (Builder $q) => $q->where('msgId', (int) $this->msgId))
                ->when(isset($this->user_id) && (string) $this->user_id !== '',
                    fn (Builder $q) => $q->where('user_id', (int) $this->user_id)))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('m/d/Y g:i:s A T', Auth::user()?->timezone ?? 'UTC')
                    ->description(fn (BoardCheckActivity $record): string => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('Unknown User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('msgId')
                    ->label('Message ID')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('activity_type')
                    ->label('Activity')
                    ->default('Unknown Activity')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->placeholder('All Users')
                    ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('10s')
            ->emptyStateHeading('There are no board check activity items.');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-activity');
    }
}
