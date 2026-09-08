<?php

namespace App\Livewire\Utilities;

use App\Models\BetterEmails as BetterEmailsModel;
use App\Models\System\Settings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

    public function deleteBetterEmail(BetterEmailsModel $betterEmail): void
    {
        $betterEmail->delete();
        $this->dispatch('saved');
    }

    /**
     * The configuration form, shared by the create and edit dialogs.
     *
     * @return array<int, mixed>
     */
    private function configurationSchema(): array
    {
        return [
            TextInput::make('client_number')->label('Client Number')->required(),
            TextInput::make('subject')->label('Email Subject')->required()->maxLength(255),
            TextInput::make('title')->required()->maxLength(100),
            TextInput::make('description')->required()->maxLength(255),

            Textarea::make('recipients')
                ->required()
                ->rows(4)
                ->helperText('One address per line.')
                // Stored as a JSON array; edited as lines of text.
                ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state)
                ->dehydrateStateUsing(fn (?string $state): array => collect(preg_split('/\r\n|\r|\n/', (string) $state))
                    ->map(fn (string $line): string => trim($line))
                    ->filter()
                    ->values()
                    ->all()),

            Toggle::make('report_metadata')->label('Include report metadata'),
            Toggle::make('message_history')->label('Include message history'),

            Select::make('theme')->options(['standard' => 'Standard'])->required()->default('standard'),

            TextInput::make('logo')->label('Logo URL')->url()->required(),
            TextInput::make('logo_alt')->label('Logo Alt Text')->required(),
            TextInput::make('logo_link')->label('Logo Link')->url()->required(),
            TextInput::make('button_text')->label('Button Text')->required()->maxLength(50),
            TextInput::make('button_link')->label('Button Link')->required(),
        ];
    }

    /**
     * System-wide defaults, used to prefill a new configuration. This replaces the
     * "Load Default" button the create dialog used to carry: there is no reason to
     * make someone ask for the defaults before editing them.
     *
     * @return array<string, mixed>
     */
    private function defaultConfiguration(): array
    {
        $settings = Settings::first();

        return [
            'title' => $settings?->better_emails_title,
            'description' => $settings?->better_emails_description,
            'report_metadata' => $settings?->better_emails_report_metadata,
            'message_history' => $settings?->better_emails_message_history,
            'theme' => $settings?->better_emails_theme ?? 'standard',
            'subject' => $settings?->better_emails_subject,
            'logo' => $settings?->better_emails_logo,
            'logo_alt' => $settings?->better_emails_logo_alt,
            'logo_link' => $settings?->better_emails_logo_link,
            'button_text' => $settings?->better_emails_button_text,
            'button_link' => $settings?->better_emails_button_link,
        ];
    }

    public function createConfigurationAction(): Action
    {
        return Action::make('createConfiguration')
            ->label('New Configuration')
            ->modalHeading('New Email Configuration')
            ->fillForm(fn (): array => $this->defaultConfiguration())
            ->schema($this->configurationSchema())
            ->action(function (array $data): void {
                $record = new BetterEmailsModel;
                $record->forceFill([...$data, 'recipients' => json_encode($data['recipients'])])->save();

                Notification::make()->title('Email configuration created.')->success()->send();
            });
    }

    public function editConfigurationAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->link()
            ->modalHeading('Edit Email Configuration')
            ->fillForm(fn (BetterEmailsModel $record): array => [
                ...$record->only([
                    'client_number', 'subject', 'title', 'description', 'report_metadata',
                    'message_history', 'theme', 'logo', 'logo_alt', 'logo_link',
                    'button_text', 'button_link',
                ]),
                'recipients' => (array) json_decode($record->recipients, true),
            ])
            ->schema($this->configurationSchema())
            ->action(function (BetterEmailsModel $record, array $data): void {
                $record->forceFill([...$data, 'recipients' => json_encode($data['recipients'])])->save();

                Notification::make()->title('Email configuration updated.')->success()->send();
            });
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
            ->headerActions([
                $this->createConfigurationAction(),
            ])
            ->recordActions([
                $this->editConfigurationAction(),

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
