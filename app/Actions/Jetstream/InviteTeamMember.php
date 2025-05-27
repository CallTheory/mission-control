<?php

namespace App\Actions\Jetstream;

use Closure;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Random\RandomException;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the given team.
     *
     * @param User $user
     * @param Team $team
     * @param string $email
     * @param string|null $role
     * @return void
     * @throws AuthorizationException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws RandomException|ValidationException
     */
    public function invite(User $user, Team $team, string $email, string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        $agtId = json_decode(request()->get('components')[0]['snapshot'], true)['data']['addTeamMemberForm'][0]['agtId'] ?? null;

        $newUser = User::where('email', $email)->first();

        if (is_null($newUser)) {
            $newUser = new User();
            $newUser->name = $email;
            $newUser->email = $email;
            $newUser->agtId = $agtId;
            $newUser->email_verified_at = now();
            $newUser->password = bcrypt(bin2hex(random_bytes(32)));
            $newUser->save();
            $newUser->fresh();

            $newUser->ownedTeams()->save(Team::forceCreate([
                'user_id' => $newUser->id,
                'name' => 'Personal',
                'personal_team' => true,
            ]));
        }

        InvitingTeamMember::dispatch($team, $email, $role);

        $invitation = $team->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]);

        Mail::to($email)->send(new TeamInvitation($invitation));
    }

    /**
     * Validate the invite member operation.
     *
     * @param Team $team
     * @param string $email
     * @param string|null $role
     * @return void
     * @throws ValidationException
     */
    protected function validate(Team $team, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($team), [
            'email.unique' => __('This user has already been invited to the team.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @param  Team  $team
     * @return array
     */
    protected function rules(Team $team): array
    {
        return array_filter([
            'email' => ['required', 'email', Rule::unique('team_invitations')->where(function ($query) use ($team) {
                $query->where('team_id', $team->id);
            })],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the team.
     *
     * @param  Team  $team
     * @param  string  $email
     * @return Closure
     */
    protected function ensureUserIsNotAlreadyOnTeam(Team $team, string $email): Closure
    {
        return function ($validator) use ($team, $email) {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }
}
