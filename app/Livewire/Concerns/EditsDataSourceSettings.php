<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

/**
 * The inline System > Data Sources panels (Intelligent Series, client database,
 * ISWeb API, marketing site, miTeamWeb, Amtelco SMTP, ...).
 *
 * Unlike the integration tiles, which open a dialog ({@see ConfiguresDataSource}),
 * these render their fields directly on the page inside a section. Both write the
 * same single DataSource row, so the reading and writing lives in
 * {@see DataSourceSettings} and this only supplies the form and its save.
 */
trait EditsDataSourceSettings
{
    use DataSourceSettings;

    /**
     * Form state. Named `data` to match Filament's default and to make clear it is
     * no longer the ad-hoc `$state` array these components used to carry.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The form components shown in the panel.
     *
     * @return array<int, mixed>
     */
    abstract protected function settingsSchema(): array;

    /**
     * Livewire calls a trait's mount hook automatically, so a using component does
     * not need a mount() of its own just to fill the form.
     */
    public function mountEditsDataSourceSettings(): void
    {
        $this->form->fill($this->currentSettings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->settingsSchema())
            ->statePath('data');
    }

    public function save(): void
    {
        $this->persistSettings($this->form->getState());

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
