<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Shared behaviour for the System settings tiles that configure the single
 * application `DataSource` row (Twilio, Stripe, Mfax, the Intelligent Series
 * connection, ...).
 *
 * Each of these screens is the same thing: a logo tile that opens a dialog holding a
 * handful of fields, saving straight back to one row. This turns that into one
 * Filament action per component -- the modal, the schema, validation, the save and
 * the confirmation notification all hang off `configureAction()`, so a component only
 * has to declare which columns it owns and what the fields look like.
 *
 * Encrypted columns are handled transparently by the model's casts, so implementations
 * work in plaintext throughout; see the EncryptedSerialized cast on DataSource.
 */
trait ConfiguresDataSource
{
    use DataSourceSettings;

    /**
     * The form components shown inside the configuration dialog.
     *
     * @return array<int, mixed>
     */
    abstract protected function settingsSchema(): array;

    /**
     * Title of the configuration dialog.
     */
    abstract protected function settingsHeading(): string;

    /**
     * Optional explanatory text shown above the fields.
     */
    protected function settingsDescription(): ?string
    {
        return null;
    }

    public function configureAction(): Action
    {
        return Action::make('configure')
            ->label($this->settingsHeading())
            ->modalHeading($this->settingsHeading())
            ->modalDescription($this->settingsDescription())
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (): array => $this->currentSettings())
            ->schema($this->settingsSchema())
            ->action(function (array $data): void {
                $this->persistSettings($data);

                Notification::make()
                    ->title('Settings saved')
                    ->success()
                    ->send();
            });
    }
}
