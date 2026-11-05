<?php

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Jobs\SyncMergeCommWebHooks;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class Tools extends Component
{
    use AuthorizesSystemComponent;

    /** Set once the sync has been queued, so the button can say so. */
    public bool $triggerSyncQueued = false;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    /**
     * Whether there is an Intelligent Series to sync from at all.
     */
    public function canSyncTriggers(): bool
    {
        return DataSource::count() > 0;
    }

    /**
     * Queue the MergeComm web trigger sync now rather than waiting for the daily run.
     *
     * The scheduled sync is daily (see App\Console\Kernel), which is right for a catalogue
     * most rules never read -- a rule with an account set routes through Amtelco SMTP
     * instead. But somebody who has just added a web trigger in Intelligent Series wants it
     * selectable immediately, and this is that button.
     */
    public function syncMergeCommTriggers(): void
    {
        $this->authorize($this->requiredCapability()->value);

        if (! $this->canSyncTriggers()) {
            return;
        }

        SyncMergeCommWebHooks::dispatch();

        $this->triggerSyncQueued = true;
    }

    public function render(): View
    {
        return view('livewire.system.tools');
    }
}
