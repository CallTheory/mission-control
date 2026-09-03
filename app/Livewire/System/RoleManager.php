<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\Role;
use App\Models\SuffixRule;
use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Team-scoped admin UI for defining roles, editing their capabilities, and
 * managing the client username-suffix grant rules. Gated by admin.manage_roles.
 */
class RoleManager extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::AdminManageRoles;
    }

    #[Locked]
    public int $teamId;

    // Role being edited (null = none open).
    public ?int $editingRoleId = null;

    /** @var array<int, string> capability values granted to the editing role */
    public array $selectedCapabilities = [];

    // New-role form.
    public string $newLabel = '';

    public string $newKey = '';

    public string $newDescription = '';

    // New suffix-rule form.
    public string $suffixPattern = '';

    public string $suffixMatchType = 'contains';

    /** @var array<int, string> */
    public array $suffixCapabilities = [];

    public function mount(): void
    {
        $this->authorize(Capability::AdminManageRoles->value);
        $this->teamId = (int) request()->user()->currentTeam->id;
    }

    protected function team(): Team
    {
        return Team::findOrFail($this->teamId);
    }

    public function editRole(int $roleId): void
    {
        $this->authorize(Capability::AdminManageRoles->value);
        $role = $this->findTeamRole($roleId);

        $this->editingRoleId = $role->id;
        $this->selectedCapabilities = $role->capabilityValues();
    }

    public function cancelEdit(): void
    {
        $this->editingRoleId = null;
        $this->selectedCapabilities = [];
    }

    public function saveCapabilities(): void
    {
        $this->authorize(Capability::AdminManageRoles->value);

        if ($this->editingRoleId === null) {
            return;
        }

        $role = $this->findTeamRole($this->editingRoleId);

        $selected = array_values(array_intersect($this->selectedCapabilities, Capability::values()));

        $this->guardAgainstLockout($role, $selected);

        $role->syncCapabilities($selected);

        $this->cancelEdit();
        $this->dispatch('saved');
    }

    public function createRole(): void
    {
        $this->authorize(Capability::AdminManageRoles->value);

        $this->newKey = Str::slug($this->newKey !== '' ? $this->newKey : $this->newLabel, '_');

        $this->validate([
            'newLabel' => ['required', 'string', 'min:2', 'max:255'],
            'newKey' => ['required', 'string', 'max:255'],
            'newDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        if (Role::where('team_id', $this->teamId)->where('key', $this->newKey)->exists()) {
            throw ValidationException::withMessages([
                'newKey' => 'A role with this key already exists on this team.',
            ]);
        }

        Role::create([
            'team_id' => $this->teamId,
            'key' => $this->newKey,
            'label' => $this->newLabel,
            'description' => $this->newDescription !== '' ? $this->newDescription : null,
            'is_system' => false,
            'sort_order' => (int) Role::where('team_id', $this->teamId)->max('sort_order') + 10,
        ]);

        $this->reset('newLabel', 'newKey', 'newDescription');
        $this->dispatch('saved');
    }

    public function deleteRole(int $roleId): void
    {
        $this->authorize(Capability::AdminManageRoles->value);
        $role = $this->findTeamRole($roleId);

        if ($role->is_system) {
            $this->addError('role', 'Built-in roles cannot be deleted.');

            return;
        }

        if ($role->users()->exists()) {
            $this->addError('role', 'This role is still assigned to one or more users.');

            return;
        }

        $role->delete();

        if ($this->editingRoleId === $roleId) {
            $this->cancelEdit();
        }

        $this->dispatch('saved');
    }

    public function addSuffixRule(): void
    {
        $this->authorize(Capability::AdminManageRoles->value);

        $this->validate([
            'suffixPattern' => ['required', 'string', 'max:255'],
            'suffixMatchType' => ['required', 'in:contains,suffix,prefix'],
            'suffixCapabilities' => ['required', 'array', 'min:1'],
            'suffixCapabilities.*' => ['in:'.implode(',', Capability::values())],
        ]);

        foreach ($this->suffixCapabilities as $capability) {
            SuffixRule::create([
                'team_id' => $this->teamId,
                'match_type' => $this->suffixMatchType,
                'pattern' => $this->suffixPattern,
                'capability' => $capability,
            ]);
        }

        $this->reset('suffixPattern', 'suffixCapabilities');
        $this->suffixMatchType = 'contains';
        $this->dispatch('saved');
    }

    public function deleteSuffixRule(int $ruleId): void
    {
        $this->authorize(Capability::AdminManageRoles->value);

        // Only team-owned rules are deletable here; global defaults are shared.
        SuffixRule::where('id', $ruleId)
            ->where('team_id', $this->teamId)
            ->delete();

        $this->dispatch('saved');
    }

    protected function findTeamRole(int $roleId): Role
    {
        return Role::where('team_id', $this->teamId)->findOrFail($roleId);
    }

    /**
     * Prevent an admin from removing the ability to manage roles — either from
     * the whole team, or from their own effective capabilities.
     *
     * @param  array<int, string>  $selected
     */
    protected function guardAgainstLockout(Role $role, array $selected): void
    {
        $manageRoles = Capability::AdminManageRoles->value;

        $wasGranted = $role->grants($manageRoles);
        $willGrant = in_array($manageRoles, $selected, true);

        if ($wasGranted && ! $willGrant) {
            // Any other role on the team still granting it?
            $otherGrants = Role::where('team_id', $this->teamId)
                ->where('id', '!=', $role->id)
                ->whereHas('capabilities', fn ($q) => $q->where('capability', $manageRoles))
                ->exists();

            if (! $otherGrants) {
                throw ValidationException::withMessages([
                    'capabilities' => 'At least one role must keep the "Manage Roles & Permissions" capability.',
                ]);
            }

            // Would the acting user lose it via one of their own roles?
            $actingUserHoldsThisRole = $role->users()
                ->whereKey(request()->user()->id)
                ->exists();

            if ($actingUserHoldsThisRole) {
                $keepsItElsewhere = request()->user()->roles()
                    ->where('roles.team_id', $this->teamId)
                    ->where('roles.id', '!=', $role->id)
                    ->whereHas('capabilities', fn ($q) => $q->where('capability', $manageRoles))
                    ->exists();

                if (! $keepsItElsewhere) {
                    throw ValidationException::withMessages([
                        'capabilities' => 'You cannot remove your own ability to manage roles.',
                    ]);
                }
            }
        }
    }

    public function render(): View
    {
        $team = $this->team();

        return view('livewire.system.role-manager', [
            'roles' => Role::where('team_id', $this->teamId)
                ->withCount('users')
                ->with('capabilities')
                ->orderBy('sort_order')
                ->get(),
            'groupedCapabilities' => Capability::grouped(),
            'teamSuffixRules' => SuffixRule::where('team_id', $this->teamId)->get(),
            'globalSuffixRules' => SuffixRule::whereNull('team_id')->get(),
        ]);
    }
}
