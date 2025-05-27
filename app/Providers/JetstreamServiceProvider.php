<?php

namespace App\Providers;

use App\Actions\AuthenticateLoginAttempt;
use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Models\User;
use Exception;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);

        try {
            $user_count = User::all()->count();
        } catch (Exception $e) {
            $user_count = 0;
        }

        if ($user_count > 0) {
            //disable the registration view after the first user is created
            Fortify::registerView(function () {
                abort(404);
            });
        }
    }

    /**
     * Configure the roles and permissions that are available within the application.
     *
     * @return void
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Fortify::authenticateUsing([new AuthenticateLoginAttempt, '__invoke']);

        Jetstream::role('admin', __('Administrator'), [
            'create', 'read', 'update', 'delete',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Administrators can perform any action and access system-level settings.'));

        Jetstream::role('manager', __('Manager'), [
            'create', 'read', 'update',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Managers have transparency into their team, including supervisors and agents.'));

        Jetstream::role('supervisor', __('Supervisor'), [
            'read', 'update',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Supervisors are allowed access to accounts and agents assigned to their team.'));

        Jetstream::role('technical', __('Technical'), [
            'read', 'update', 'create', 'delete',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Technical users are allowed access to the dashboard, system, and utilities sections. They cannot view individual users data (outside of aggregated analytics)'));

        Jetstream::role('dispatcher', __('Dispatcher'), [
            'read',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Dispatchers can see all call data and make use of utilities.'));

        Jetstream::role('agent', __('Agent'), [
            'read',
            'dashboard',
            'utilities',
            'system',
        ])->description(__('Agents can see their own statistics.'));
    }
}
