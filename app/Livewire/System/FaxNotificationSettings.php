<?php

namespace App\Livewire\System;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class FaxNotificationSettings extends Component
{
    public array $state;

    public DataSource $datasource;

    public function saveFaxNotificationSettings(): void
    {
        $this->datasource->fax_buildup_notification_email = $this->state['fax_buildup_notification_email'];
        $this->datasource->fax_failure_notification_email = $this->state['fax_failure_notification_email'];
        $this->datasource->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrFail();

        $this->state['fax_buildup_notification_email'] = $this->datasource->fax_buildup_notification_email ?? null;
        $this->state['fax_failure_notification_email'] = $this->datasource->fax_failure_notification_email ?? null;
    }

    public function render(): View
    {
        return view('livewire.system.fax-notification-settings');
    }
}
