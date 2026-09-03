<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Capability;

/**
 * Enforces a capability on every Livewire request for a System settings
 * component — not just on the initial mount.
 *
 * Livewire does NOT re-apply a controller's `authorize()` call when the browser
 * POSTs to /livewire/update, so a component whose only gate lives in its page
 * controller keeps accepting actions from a user whose capability has since
 * been revoked, and is unprotected if it is ever embedded on a page with a
 * weaker gate.
 *
 * `boot{TraitName}` is deliberate: Livewire calls the trait `boot` hook from
 * both mount() and hydrate() (see SupportLifecycleHooks), so this runs on the
 * initial render AND on every subsequent update.
 *
 * Note this is defense-in-depth, not a replacement for the controller gate:
 * Livewire swallows the resulting 403 during a full-page render, so the
 * controller is still what produces a real 403 for the page itself.
 */
trait AuthorizesSystemComponent
{
    /**
     * The capability a user must hold to interact with this component. Should
     * match the capability gating the page(s) that embed it.
     */
    abstract protected function requiredCapability(): Capability;

    public function bootAuthorizesSystemComponent(): void
    {
        $this->authorize($this->requiredCapability()->value);
    }
}
