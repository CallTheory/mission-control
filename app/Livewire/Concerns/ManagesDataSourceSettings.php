<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\DataSource;

/**
 * Shared lifecycle for the System DataSources/Integrations settings forms, all of
 * which edit the single application `DataSource` row.
 *
 * A using component declares the fields it owns via `protected array $settingsFields`
 * (or by overriding settingsFields()); this trait hydrates `$state` from the row on
 * mount and persists it back on save. Encrypted columns are handled transparently by
 * the model's casts, so callers work in plaintext.
 *
 * Components with bespoke needs (e.g. "leave blank to keep the stored secret", or a
 * testConnection() action) keep those in the child and simply don't route through
 * persistSettings() for the special fields.
 */
trait ManagesDataSourceSettings
{
    public array $state = [];

    public function mountManagesDataSourceSettings(): void
    {
        $datasource = DataSource::firstOrNew();

        foreach ($this->settingsFields() as $field) {
            $this->state[$field] = $datasource->{$field} ?? '';
        }
    }

    /**
     * @return array<int, string>
     */
    protected function settingsFields(): array
    {
        return property_exists($this, 'settingsFields') ? $this->settingsFields : [];
    }

    protected function persistSettings(): DataSource
    {
        $datasource = DataSource::firstOrNew();

        foreach ($this->settingsFields() as $field) {
            $value = $this->state[$field] ?? null;
            $datasource->{$field} = ($value === '' ? null : $value);
        }

        $datasource->save();
        $this->dispatch('saved');

        return $datasource;
    }
}
