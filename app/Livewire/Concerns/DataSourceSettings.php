<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\DataSource;

/**
 * Reading and writing the single application `DataSource` row.
 *
 * Shared by the two shapes the System settings screens take: the integration tiles,
 * which open a dialog ({@see ConfiguresDataSource}), and the data source panels,
 * which are inline forms ({@see EditsDataSourceSettings}).
 *
 * Encrypted columns are handled transparently by the model's casts, so everything
 * here works in plaintext; see the EncryptedSerialized cast on DataSource.
 */
trait DataSourceSettings
{
    /**
     * The DataSource columns this component owns.
     *
     * @return array<int, string>
     */
    abstract protected function settingsFields(): array;

    /**
     * Fields that are written only when a value is actually supplied, so submitting
     * the form with the field left blank keeps whatever is already stored. This is
     * how the password and secret inputs behave: they are never prefilled, because
     * rendering a stored credential back into the page would put it in the DOM and
     * in the Livewire payload.
     *
     * @return array<int, string>
     */
    protected function preservedFields(): array
    {
        return [];
    }

    /**
     * Current values, with preserved fields blanked so they are not sent to the
     * browser.
     *
     * @return array<string, mixed>
     */
    protected function currentSettings(): array
    {
        $datasource = DataSource::firstOrNew();
        $preserved = $this->preservedFields();

        $state = [];

        foreach ($this->settingsFields() as $field) {
            $state[$field] = in_array($field, $preserved, true)
                ? ''
                : $datasource->{$field};
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistSettings(array $data): DataSource
    {
        return $this->persistDataSourceSettings($data);
    }

    /**
     * The shared write. Kept separate from persistSettings() so a component with
     * extra save behaviour (Mfax enabling itself on first configuration) can override
     * persistSettings() and still call this rather than reimplementing it.
     *
     * @param  array<string, mixed>  $data
     */
    final protected function persistDataSourceSettings(array $data): DataSource
    {
        $datasource = DataSource::firstOrNew();
        $preserved = $this->preservedFields();

        foreach ($this->settingsFields() as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            // Blank on a preserved field means "leave it as it is", not "clear it".
            if (in_array($field, $preserved, true) && blank($value)) {
                continue;
            }

            // Elsewhere an empty field means "not configured", which the columns
            // express as NULL rather than an empty string.
            $datasource->{$field} = ($value === '' ? null : $value);
        }

        $datasource->save();

        // Kept for the Blade `x-action-message on="saved"` wiring that predates this.
        $this->dispatch('saved');

        return $datasource;
    }
}
