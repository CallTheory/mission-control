<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsDataSourceSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class FaxNotificationSettings extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use EditsDataSourceSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    protected function settingsFields(): array
    {
        return ['fax_buildup_notification_email', 'fax_failure_notification_email'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('fax_buildup_notification_email')
                ->label('Queue Buildup Notification')
                ->email()
                ->helperText('Notified when the outbound fax queue backs up.')
                ->validationAttribute('queue buildup notification address'),

            TextInput::make('fax_failure_notification_email')
                ->label('Failure Notification')
                ->email()
                ->helperText('Notified when a fax fails to send.')
                ->validationAttribute('failure notification address'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.fax-notification-settings');
    }
}
