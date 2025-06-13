<?php

namespace App\Livewire\System;

use App\Models\Stats\Agents\Listing;
use App\Models\Team;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Laravel\Jetstream\Jetstream;
use Livewire\Attributes\Locked;
use Livewire\Component;

class User extends Component
{
    #[Locked]
    public mixed $user;

    public array $agents;

    public array $roles;

    public Collection $teams;

    public ?int $new_team;

    public ?int $user_agtId;

    public string $new_role;

    public string $user_name;

    public string $user_email;

    public string $user_timezone;

    public bool $confirmingUserDeletion = false;

    protected $listeners = ['saved' => '$refresh', 'assigned' => '$refresh'];

    public function deleteUser()
    {

        $this->clearValidation();

        foreach ($this->user->ownedTeams as $team) {
            if (! $team->personal_team) {
                $this->addError('delete_user', 'A user cannot be deleted if they own a team (personal teams are excluded)');
                exit;
            }
        }

        foreach ($this->user->allTeams() as $team) {
            $team->removeUser($this->user);
            TeamMemberRemoved::dispatch($team, $this->user);

            if ($team->personal_team) {
                $team->delete();
            }
        }

        $this->user->removeApplicationData();
        $this->user->delete();

        return redirect()->route('system.users');
    }

    public function confirmUserDeletion(): void
    {
        $this->confirmingUserDeletion = true;
    }

    public function saveUserDetails(): void
    {

        $this->clearValidation();
        $this->validate([
            'user_name' => 'required|min:3|max:255',
            'user_email' => 'required|email',
            'user_timezone' => 'required',
            'user_agtId' => 'nullable',
        ]);

        try {
            $this->user->name = $this->user_name;
            $this->user->email = $this->user_email;
            $this->user->timezone = $this->user_timezone;
            $this->user->agtId = $this->user_agtId ?? null;
            $this->user->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('user_name', $e->getMessage());
        }
    }

    public function assignTeamAndRole(): void
    {

        $this->clearValidation();
        try {
            $team = Team::findOrFail($this->new_team);
        } catch (Exception $e) {
            $this->addError('new_team', 'Matching team not found');

            return;
        }

        try {
            $role = Jetstream::$roles[$this->new_role];
        } catch (Exception $e) {
            $this->addError('new_role', 'Matching role not found');

            return;
        }

        if ($this->user->ownsTeam($team)) {
            $this->addError('new_team', 'User owns this team, you cannot change roles.');

            return;
        }

        if ($this->user->belongsToTeam($team)) {
            if ($this->user->hasTeamRole($team, $role->key)) {
                // they are on the team already
                // they have the role being assigned already
                $this->addError('new_team', 'User already has this role on this team');
            } else {
                $team->users()->updateExistingPivot($this->user->id, [
                    'role' => $role->key,
                ]);
            }
        } else {
            $team->users()->attach($this->user->id, [
                'role' => $role->key,
            ]);
        }

        $this->dispatch('assigned');
    }

    public function removeFromTeam(Team $team): void
    {

        $this->clearValidation();
        if ($this->user->ownsTeam($team)) {
            $this->addError('remove_error', 'User owns this team, you cannot remove them.');

            return;
        } else {
            $team->removeUser($this->user);

            TeamMemberRemoved::dispatch($team, $this->user);
        }
        $this->dispatch('saved');
    }

    public function mount($user): void
    {
        $this->user = $user;
        $this->user_name = $user->name;
        $this->user_email = $user->email;
        $this->user_timezone = $user->timezone;
        $this->user_agtId = $user->agtId ?? null;

        try {
            $agentList = new Listing(['all' => true]);
            $this->agents = $agentList->results;
        } catch (Exception $e) {
            $this->agents = [];
        }
        $this->roles = array_keys(Jetstream::$roles);
        $this->teams = Team::where('personal_team', false)->get();
    }

    public function render(): View
    {
        return view('livewire.system.user');
    }
}
