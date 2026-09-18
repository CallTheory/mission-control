<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Capability;
use App\Support\WctpSectionAccess;

/**
 * Authorization for the WCTP gateway section's Livewire components.
 *
 * Every component re-authorizes on mount, on render and inside each action:
 * Livewire does not re-apply the controller's check on `POST /livewire/update`, so an
 * action is reachable independently of the page that rendered it.
 *
 * Replaces AuthorizesWctpManagement, which also carried the team scoping these
 * screens no longer do -- the gateway is one installation-wide configuration.
 *
 * @see WctpSectionAccess for the rule itself
 */
trait AuthorizesWctpSection
{
    /**
     * The capability this component requires.
     */
    abstract protected function wctpCapability(): Capability;

    protected function authorizeWctpSection(): void
    {
        WctpSectionAccess::authorize($this->wctpCapability());
    }
}
