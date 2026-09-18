<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Enums\Capability;
use App\Livewire\System\AzureTokens\Alerting;
use App\Livewire\System\AzureTokens\Credentials;
use App\Livewire\System\AzureTokens\Overview;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * Who can reach the token dashboard.
 *
 * The page exposes the credential posture of the whole Entra tenant, which is
 * administrative regardless of team, so it is gated like Observability: a plain
 * capability held by the admin role, with no per-team utility flag.
 */
class PageAccessTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    public function test_an_administrator_can_open_the_page(): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'admin'));

        $this->get(route('system.azure-tokens'))->assertOk();
    }

    public static function deniedRoles(): array
    {
        return [
            'manager' => ['manager'],
            'supervisor' => ['supervisor'],
            'technical' => ['technical'],
            'dispatcher' => ['dispatcher'],
            'agent' => ['agent'],
        ];
    }

    #[DataProvider('deniedRoles')]
    public function test_every_other_role_is_denied(string $role): void
    {
        $this->actingAs($this->createUserWithRole($this->team, $role));

        $this->get(route('system.azure-tokens'))->assertForbidden();
    }

    public function test_an_administrator_without_the_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'admin', Capability::SystemAzureTokens));

        $this->get(route('system.azure-tokens'))->assertForbidden();
    }

    /**
     * Livewire does not re-apply the controller's authorize() on
     * POST /livewire/update, so each component carries its own gate.
     *
     * @return array<string, array<int, class-string>>
     */
    public static function components(): array
    {
        return [
            'overview' => [Overview::class],
            'credentials' => [Credentials::class],
            'alerting' => [Alerting::class],
        ];
    }

    #[DataProvider('components')]
    public function test_components_reject_a_user_without_the_capability(string $component): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'manager'));

        Livewire::test($component)->assertForbidden();
    }

    #[DataProvider('components')]
    public function test_components_render_for_an_administrator(string $component): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'admin'));

        Livewire::test($component)->assertOk();
    }
}
