<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Support\TimezoneOptions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm as JetstreamUpdateProfileInformationForm;

/**
 * Jetstream's profile form, with the timezone <datalist> replaced by the same
 * searchable Filament Select used at System > Switch Data Timezone.
 *
 * Name, email and photo stay on Jetstream's `$state` array and its own blade
 * inputs -- only the timezone moves onto a schema, under its own `$data` state
 * path so that Filament filling and Jetstream's `mount()` cannot tread on each
 * other. `updateProfileInformation()` folds the schema's value back into
 * `$state` before handing off, so validation and persistence still run through
 * {@see UpdateUserProfileInformation} unchanged.
 */
class UpdateProfileInformationForm extends JetstreamUpdateProfileInformationForm implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Schema state. Holds the timezone only.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'timezone' => $this->state['timezone'] ?? config('app.timezone'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('timezone')
                    ->label(__('Timezone'))
                    ->options(TimezoneOptions::grouped())
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->helperText(__('Times throughout Mission Control are shown in this timezone.'))
                    ->validationAttribute(__('timezone')),
            ])
            ->statePath('data');
    }

    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->state['timezone'] = $this->form->getState()['timezone'] ?? null;

        return parent::updateProfileInformation($updater);
    }
}
