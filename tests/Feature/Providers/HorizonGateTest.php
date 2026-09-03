<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Enums\Capability;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class HorizonGateTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    public function test_admin_can_view_horizon(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');

        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
    }

    public function test_user_without_system_access_cannot_view_horizon(): void
    {
        // The regression this replaces: the gate used to be `$user->id > 0`, so
        // every authenticated user could retry and delete queued jobs at /queue.
        $agent = $this->createUserWithout($this->team, 'agent', Capability::SystemAccess);

        $this->assertFalse(Gate::forUser($agent)->allows('viewHorizon'));
    }
}
