<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Users extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected function requiredCapability(): Capability
    {
        return Capability::AdminManageUsers;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->with(['teams', 'ownedTeams']))
            ->columns([
                ImageColumn::make('profile_photo_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl('/images/call-theory.svg'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('teams')
                    ->label('Teams')
                    ->badge()
                    // allTeams() merges owned and joined teams, so it is a Collection
                    // rather than a relation and cannot be reached by dot notation.
                    ->state(fn (User $record): array => $record->allTeams()->pluck('name')->all())
                    ->color(fn (string $state, User $record): string => $record->allTeams()
                        ->firstWhere('name', $state)?->personal_team
                            ? 'gray'
                            : 'primary'),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (User $record): string => route('system.user.{user}', $record))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('No users');
    }

    public function render(): View
    {
        return view('livewire.system.users');
    }
}
