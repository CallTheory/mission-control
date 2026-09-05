<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\DataSource;
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
    /**
     * The DataSource columns this component owns, as a plain list.
     *
     * @return array<int, string>
     */
    abstract protected function settingsFields(): array;

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

    /**
     * @return array<string, mixed>
     */
    protected function currentSettings(): array
    {
        $datasource = DataSource::firstOrNew();

        $state = [];

        foreach ($this->settingsFields() as $field) {
            $state[$field] = $datasource->{$field};
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistSettings(array $data): DataSource
    {
        $datasource = DataSource::firstOrNew();

        foreach ($this->settingsFields() as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            // An empty field means "not configured", which the columns express as
            // NULL rather than an empty string.
            $datasource->{$field} = ($value === '' ? null : $value);
        }

        $datasource->save();

        // Kept for the Blade `x-action-message on="saved"` wiring that predates this.
        $this->dispatch('saved');

        return $datasource;
    }
}
