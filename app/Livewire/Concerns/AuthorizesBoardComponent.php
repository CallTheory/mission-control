<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Capability;

/**
 * Enforces a capability on every Livewire request for a board check modal.
 *
 * These are wire-elements ModalComponents, which are ordinary registered Livewire
 * components: the browser can mount one and invoke its actions by POSTing to
 * /livewire/update, whether or not any page links to it. Four of them
 * (BoardApproveMessage, BoardConfirmProblem, BoardFlagIssue, BoardMessageOk) are
 * currently opened from nowhere at all, and were still reachable that way.
 *
 * Gating the page that opens a modal is therefore not enough, for the same reason
 * documented on {@see AuthorizesSystemComponent}: Livewire does not re-apply a
 * controller's authorize() on the update request.
 *
 * `boot{TraitName}` is deliberate -- Livewire calls the trait boot hook from both
 * mount() and hydrate(), so this runs on the initial open AND on every action.
 */
trait AuthorizesBoardComponent
{
    /**
     * The capability a user must hold to interact with this modal. Dispatcher-stage
     * modals (those writing approved_at / problem_found_at) want the board check
     * capability; supervisor-stage modals (marked_ok_at / problem_verified_at) want
     * the board review capability.
     */
    abstract protected function requiredCapability(): Capability;

    public function bootAuthorizesBoardComponent(): void
    {
        $this->authorize($this->requiredCapability()->value);
    }
}
