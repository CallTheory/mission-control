<?php

declare(strict_types=1);

namespace App\Livewire\Teams;

use App\Models\Stats\Agents\Listing;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;
use Livewire\Component;

class EditUserProfile extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $user_id;

    /** The name shown as the dialog's trigger. */
    public string $user_name = '';

    public function mount($user): void
    {
        $this->user_id = $user->id;
        $this->user_name = $user->name;
    }

    public function editProfileAction(): Action
    {
        return Action::make('editProfile')
            ->label(fn (): string => $this->user_name)
            ->link()
            ->modalHeading('Edit Profile')
            ->fillForm(function (): array {
                $user = User::findOrFail($this->user_id);

                return ['name' => $user->name, 'email' => $user->email, 'agtId' => $user->agtId];
            })
            ->schema([
                TextInput::make('name')->required()->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Filament's unique() wants a bound record; this component holds an
                    // id, so the ignore is expressed directly.
                    ->rule(fn (): Unique => Rule::unique('users', 'email')->ignore($this->user_id)),

                Select::make('agtId')
                    ->label('Amtelco Agent')
                    ->options(fn (): array => $this->agentOptions())
                    ->searchable()
                    ->placeholder('Not linked')
                    ->helperText('Links this account to an Amtelco agent for analytics.'),
            ])
            ->action(function (array $data): void {
                $user = User::findOrFail($this->user_id);

                $user->name = $data['name'];
                $user->email = $data['email'];
                // An unlinked account stores NULL rather than an empty string.
                $user->agtId = blank($data['agtId'] ?? null) ? null : $data['agtId'];
                $user->save();

                $this->user_name = $user->name;

                Notification::make()->title('Profile updated.')->success()->send();
            });
    }

    /**
     * @return array<int|string, string>
     */
    private function agentOptions(): array
    {
        try {
            return collect((new Listing)->results)->pluck('Name', 'agtId')->all();
        } catch (Exception) {
            // The agent list comes from Amtelco; an unreachable database leaves the
            // select empty rather than blocking the edit.
            return [];
        }
    }

    public function render(): View
    {
        return view('livewire.teams.edit-user-profile');
    }
}
