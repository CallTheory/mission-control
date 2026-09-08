<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\AuthorizesWctpManagement;
use App\Models\EnterpriseHost;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

class EnterpriseHostManagement extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesWctpManagement;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $showModal = false;

    public $editingHost = null;

    // Form fields
    public $name = '';

    public $senderID = '';

    public $securityCode = '';

    public $enabled = true;

    public $callback_url = '';

    public $team_id = null;

    public $phoneNumbers = [];

    public $newPhoneNumber = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'senderID' => 'required|string|max:255',
        'securityCode' => 'required|string|min:8',
        'enabled' => 'boolean',
        'callback_url' => 'nullable|url',
        'phoneNumbers' => 'array',
        'phoneNumbers.*' => 'string|regex:/^[\+]?[1-9]\d{1,14}$/',
    ];

    public function mount()
    {
        $this->authorizeWctpManagement();
    }

    public function table(Table $table): Table
    {
        return $table
            // Hosts are always scoped to the acting team; the client cannot widen this.
            ->query(fn (): Builder => EnterpriseHost::query()
                ->where('team_id', $this->currentTeamId())
                ->withCount('messages'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(function (EnterpriseHost $record): ?string {
                        $count = count($record->phone_numbers ?? []);

                        return $count > 0 ? $count.' number'.($count === 1 ? '' : 's') : null;
                    }),

                TextColumn::make('senderID')
                    ->label('Sender ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('last_message_at')
                    ->label('Last Activity')
                    ->since()
                    ->placeholder('Never')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('enabled')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->link()
                    ->action(fn (EnterpriseHost $record) => $this->editHost($record)),

                Action::make('toggleEnabled')
                    ->label(fn (EnterpriseHost $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(fn (EnterpriseHost $record) => $this->toggleEnabled($record)),

                Action::make('messages')
                    ->label('Messages')
                    ->link()
                    ->url(fn (EnterpriseHost $record): string => route('utilities.wctp-messages', ['host' => $record->id])),

                Action::make('delete')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this enterprise host?')
                    // A host with messages cannot be deleted, only disabled.
                    ->visible(fn (EnterpriseHost $record): bool => $record->messages_count === 0)
                    ->action(fn (EnterpriseHost $record) => $this->deleteHost($record)),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No enterprise hosts found.');
    }

    public function render()
    {
        $this->authorizeWctpManagement();

        return view('livewire.utilities.enterprise-host-management');
    }

    public function createHost()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editHost(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        $this->editingHost = $host;
        $this->name = $host->name;
        $this->senderID = $host->senderID;
        $this->securityCode = ''; // Don't show existing encrypted code
        $this->enabled = $host->enabled;
        $this->callback_url = $host->callback_url ?? '';
        $this->team_id = $host->team_id;
        $this->phoneNumbers = $host->phone_numbers ?? [];
        $this->newPhoneNumber = '';

        $this->showModal = true;
    }

    public function save()
    {
        $this->authorizeWctpManagement();

        $this->validate();

        $data = [
            'name' => $this->name,
            'senderID' => $this->senderID,
            'enabled' => $this->enabled,
            'callback_url' => $this->callback_url ?: null,
            // Ownership is always the acting team — never trust a client-supplied team_id.
            'team_id' => $this->currentTeamId(),
            'phone_numbers' => array_values($this->phoneNumbers), // Ensure it's a sequential array
        ];

        if ($this->editingHost) {
            // Re-authorize the target on write: the bound model must belong to the team.
            $this->authorizeHost($this->editingHost);

            // Only update security code if a new one was provided
            if ($this->securityCode) {
                $data['securityCode'] = $this->securityCode;
            }

            $this->editingHost->update($data);

            Notification::make()
                ->title('Enterprise Host updated successfully.')
                ->success()
                ->send();
        } else {
            // Validate unique senderID for new hosts
            $this->validate([
                'senderID' => 'unique:enterprise_hosts,senderID',
            ]);

            $data['securityCode'] = $this->securityCode;

            EnterpriseHost::create($data);

            Notification::make()
                ->title('Enterprise Host created successfully.')
                ->success()
                ->send();
        }

        $this->resetForm();
        $this->showModal = false;
    }

    public function deleteHost(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        if ($host->messages()->exists()) {
            Notification::make()
                ->title('Cannot delete host with existing messages. Disable it instead.')
                ->danger()
                ->send();

            return;
        }

        $host->delete();

        Notification::make()
            ->title('Enterprise Host deleted successfully.')
            ->success()
            ->send();
    }

    public function toggleEnabled(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        $host->update(['enabled' => ! $host->enabled]);

        $status = $host->enabled ? 'enabled' : 'disabled';
        Notification::make()
            ->title("Enterprise Host {$status} successfully.")
            ->success()
            ->send();
    }

    public function generateSecurityCode()
    {
        $this->securityCode = Str::random(16);
    }

    public function addPhoneNumber()
    {
        $this->validate(['newPhoneNumber' => 'required|regex:/^[\+]?[1-9]\d{1,14}$/']);

        // Normalize the phone number
        $normalized = preg_replace('/\D+/', '', $this->newPhoneNumber);
        if (! str_starts_with($normalized, '1') && strlen($normalized) == 10) {
            $normalized = '1'.$normalized;
        }
        $formatted = '+'.$normalized;

        if (! in_array($formatted, $this->phoneNumbers)) {
            $this->phoneNumbers[] = $formatted;
        }

        $this->newPhoneNumber = '';
    }

    public function removePhoneNumber($index)
    {
        unset($this->phoneNumbers[$index]);
        $this->phoneNumbers = array_values($this->phoneNumbers);
    }

    public function resetForm()
    {
        $this->reset([
            'name',
            'senderID',
            'securityCode',
            'enabled',
            'callback_url',
            'team_id',
            'phoneNumbers',
            'newPhoneNumber',
            'editingHost',
        ]);

        $this->resetValidation();
        $this->showModal = false;
    }

    public function viewMessages(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        return redirect()->route('utilities.wctp-messages', ['host' => $host->id]);
    }

    /**
     * Ensure the bound host belongs to the acting team before any mutation or
     * navigation. Guards against tampered route-model-bound ids.
     */
    protected function authorizeHost(EnterpriseHost $host): void
    {
        $this->authorizeWctpManagement();

        if ((int) $host->team_id !== $this->currentTeamId()) {
            abort(403);
        }
    }
}
