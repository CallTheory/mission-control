<?php

namespace App\Livewire\Utilities;

use App\Models\BetterEmails as BetterEmailsModel;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class BetterEmails extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $editingRecord = 0;

    public array $state = [];

    public $listeners = ['saved' => '$refresh'];

    public function editBetterEmail(BetterEmailsModel $betterEmail): void
    {

        $this->state['client_number'] = $betterEmail->client_number;
        $this->state['subject'] = $betterEmail->subject;
        $this->state['title'] = $betterEmail->title;
        $this->state['description'] = $betterEmail->description;
        $this->state['recipients'] = implode("\n", json_decode($betterEmail->recipients));
        $this->state['report_metadata'] = $betterEmail->report_metadata;
        $this->state['message_history'] = $betterEmail->message_history;
        $this->state['theme'] = $betterEmail->theme;
        $this->state['logo'] = $betterEmail->logo;
        $this->state['logo_alt'] = $betterEmail->logo_alt;
        $this->state['logo_link'] = $betterEmail->logo_link;
        $this->state['button_text'] = $betterEmail->button_text;
        $this->state['button_link'] = $betterEmail->button_link;
        $this->editingRecord = $betterEmail->id;

    }

    public function closeEditModal(): void
    {
        $this->state = [];
        $this->editingRecord = 0;
    }

    public function updateBetterEmail(BetterEmailsModel $betterEmail): void
    {

        $this->validate([
            'state.client_number' => 'required',
            'state.title' => 'required|string|max:100',
            'state.description' => 'required|string|max:255',
            'state.recipients' => 'required',
            'state.report_metadata' => 'required|boolean',
            'state.message_history' => 'required|boolean',
            'state.theme' => 'required|string|in:standard',
            'state.subject' => 'required|max:255',
            'state.logo' => 'required|url',
            'state.logo_alt' => 'required|string',
            'state.logo_link' => 'required|url',
            'state.button_text' => 'required|string|max:50',
            'state.button_link' => 'required|string',
        ]);

        $betterEmail->client_number = $this->state['client_number'];
        $betterEmail->subject = $this->state['subject'];
        $betterEmail->title = $this->state['title'];
        $betterEmail->description = $this->state['description'];
        $betterEmail->recipients = json_encode(explode("\n", $this->state['recipients']));
        $betterEmail->report_metadata = $this->state['report_metadata'];
        $betterEmail->message_history = $this->state['message_history'];
        $betterEmail->theme = $this->state['theme'];
        $betterEmail->logo = $this->state['logo'];
        $betterEmail->logo_alt = $this->state['logo_alt'];
        $betterEmail->logo_link = $this->state['logo_link'];
        $betterEmail->button_text = $this->state['button_text'];
        $betterEmail->button_link = $this->state['button_link'];
        $betterEmail->save();
        $this->state = [];
        $this->editingRecord = 0;
        $this->dispatch('saved');
    }

    public function deleteBetterEmail(BetterEmailsModel $betterEmail): void
    {
        $betterEmail->delete();
        $this->dispatch('saved');
    }

    public function table(Table $table): Table
    {
        // better_emails has no team_id column -- these configurations are system-wide
        // by schema. Access is gated upstream by the team's utility_better_emails flag.
        return $table
            ->query(fn (): Builder => BetterEmailsModel::query())
            ->columns([
                TextColumn::make('client_number')
                    ->label('Client Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Email Subject')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('recipients')
                    ->label('Recipients')
                    ->badge()
                    ->state(fn (BetterEmailsModel $record): array => (array) json_decode($record->recipients, true)),

                TextColumn::make('drop_location')
                    ->label('Drop Location')
                    ->color('gray')
                    ->fontFamily('mono')
                    ->state(fn (BetterEmailsModel $record): string => config('app.unc_path')
                        .'\\better-emails\\'.$record->client_number.'\\'.$record->id),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->link()
                    ->action(fn (BetterEmailsModel $record) => $this->editBetterEmail($record)),

                Action::make('delete')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this email configuration?')
                    ->action(fn (BetterEmailsModel $record) => $this->deleteBetterEmail($record)),
            ])
            ->defaultSort('client_number')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No email configurations found.');
    }

    public function render(): View
    {
        return view('livewire.utilities.better-emails');
    }
}
