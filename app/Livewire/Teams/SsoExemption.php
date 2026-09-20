<?php

declare(strict_types=1);

namespace App\Livewire\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Marks a team as one whose members will never have an identity-provider
 * account -- third-party users, contractors, and the like.
 *
 * Exempting a team lifts the linked-SSO lock for its members. It does not lift
 * the SSO-or-2FA policy: those users land on two-factor instead, which is the
 * point of exempting them rather than switching the policy off.
 *
 * A user in several teams is only exempt when *every* one of their teams is,
 * so adding someone to an exempt team cannot quietly loosen their access
 * elsewhere. See App\Support\AuthPolicy.
 */
class SsoExemption extends Component
{
    public Team $team;

    public bool $ssoExempt = false;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->ssoExempt = (bool) $team->sso_exempt;
    }

    public function toggleSsoExemption(): void
    {
        Gate::authorize('update', $this->team);

        $this->ssoExempt = ! $this->ssoExempt;
        $this->team->sso_exempt = $this->ssoExempt;
        $this->team->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.teams.sso-exemption');
    }
}
