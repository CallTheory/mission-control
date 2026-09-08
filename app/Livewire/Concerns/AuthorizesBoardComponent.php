<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Capability;

/**
 * Enforces a capability on every Livewire request for a board check screen.
 *
 * Gating the page is not enough, for the same reason documented on
 * {@see AuthorizesSystemComponent}: Livewire does not re-apply a controller's
 * authorize() on POST /livewire/update. A board listing is an ordinary registered
 * Livewire component, so the browser can drive it -- and the review action on its
 * rows, which writes board check items -- directly, whatever the page did.
 *
 * `boot{TraitName}` is deliberate -- Livewire calls the trait boot hook from both
 * mount() and hydrate(), so this runs on the initial render AND on every action.
 */
trait AuthorizesBoardComponent
{
    /**
     * The capability a user must hold to interact with this screen, matching the
     * capability that gates the page it lives on.
     */
    abstract protected function requiredCapability(): Capability;

    public function bootAuthorizesBoardComponent(): void
    {
        $this->authorize($this->requiredCapability()->value);
    }
}
