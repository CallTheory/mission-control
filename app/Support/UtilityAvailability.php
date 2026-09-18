<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Capability;
use App\Enums\Utility;
use App\Models\Stats\Helpers;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Why the Utilities index has nothing to show.
 *
 * A utility tile appears only when all of system flag, team column, non-personal
 * team and the user's capability line up -- see the gate built in
 * AuthServiceProvider::registerCapabilityGates(). When the grid comes out empty
 * the page cannot say anything useful without knowing WHICH of those failed,
 * because the fix is in a different place each time: System settings, the team's
 * own settings, or the role a user holds.
 *
 * So this walks the same four conditions the gate does, in the order an operator
 * would have to fix them, and names the first one that is blocking.
 */
class UtilityAvailability
{
    public const REASON_PERSONAL_TEAM = 'personal-team';

    public const REASON_NONE_SYSTEM = 'none-system';

    public const REASON_NONE_TEAM = 'none-team';

    public const REASON_NO_CAPABILITY = 'no-capability';

    public const REASON_MISSING_DEPENDENCY = 'missing-dependency';

    public function __construct(private readonly User $user) {}

    /**
     * The utilities this user can actually open right now.
     *
     * @return array<int, Utility>
     */
    public function visible(): array
    {
        return array_values(array_filter(
            Utility::cases(),
            fn (Utility $utility): bool => Gate::forUser($this->user)
                ->allows($utility->capability()->value)
                && $this->hasWorkingDependencies($utility)
        ));
    }

    public function hasAny(): bool
    {
        return $this->visible() !== [];
    }

    /**
     * The utilities the gate allows this user, ignoring whether each one's own
     * dependencies are satisfied. The difference between this and visible() is
     * what separates "your role" from "not configured yet".
     *
     * @return array<int, Utility>
     */
    private function granted(): array
    {
        return array_values(array_filter(
            Utility::cases(),
            fn (Utility $utility): bool => Gate::forUser($this->user)
                ->allows($utility->capability()->value)
        ));
    }

    /**
     * The first blocking condition, or null when something is visible.
     */
    public function reason(): ?string
    {
        if ($this->hasAny()) {
            return null;
        }

        $team = $this->user->currentTeam;

        if ($team === null || $team->personal_team === true) {
            return self::REASON_PERSONAL_TEAM;
        }

        if ($this->enabledForSystem() === []) {
            return self::REASON_NONE_SYSTEM;
        }

        if ($this->enabledForTeam($team) === []) {
            return self::REASON_NONE_TEAM;
        }

        if ($this->granted() === []) {
            return self::REASON_NO_CAPABILITY;
        }

        // Granted, but every one of them is held back by something it depends
        // on -- today only Cloud Faxing, which needs a provider configured.
        // Blaming the user's role here would send them to the wrong screen.
        return self::REASON_MISSING_DEPENDENCY;
    }

    /**
     * Where this user should be sent to fix it, or null when the fix is not
     * theirs to make -- in which case the view tells them who to ask.
     */
    public function fixRoute(): ?string
    {
        $team = $this->user->currentTeam;

        return match ($this->reason()) {
            self::REASON_NONE_SYSTEM => Gate::forUser($this->user)->allows(Capability::SystemAccess->value)
                ? route('system')
                : null,
            self::REASON_NONE_TEAM => $team !== null && Gate::forUser($this->user)->allows('update', $team)
                ? route('teams.show', $team)
                : null,
            self::REASON_NO_CAPABILITY => Gate::forUser($this->user)->allows(Capability::AdminManageRoles->value)
                ? route('system.permissions')
                : null,
            self::REASON_MISSING_DEPENDENCY => Gate::forUser($this->user)->allows(Capability::SystemAccess->value)
                ? route('system')
                : null,
            default => null,
        };
    }

    /**
     * @return array<int, Utility>
     */
    private function enabledForSystem(): array
    {
        return array_values(array_filter(
            Utility::cases(),
            fn (Utility $utility): bool => Helpers::isSystemFeatureEnabled($utility->systemFlag())
        ));
    }

    /**
     * @return array<int, Utility>
     */
    private function enabledForTeam(Team $team): array
    {
        return array_values(array_filter(
            $this->enabledForSystem(),
            fn (Utility $utility): bool => (bool) ($team->{$utility->teamColumn()})
        ));
    }

    /**
     * Cloud Faxing is gated a second time on a provider being configured, so a
     * team whose only utility is Cloud Faxing with no provider still sees an
     * empty grid. Without this the page would insist everything is fine.
     */
    private function hasWorkingDependencies(Utility $utility): bool
    {
        if ($utility === Utility::CloudFaxing) {
            return Helpers::anyCloudFaxProviderEnabled();
        }

        return true;
    }
}
