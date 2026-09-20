<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\ApiTokenManager;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenAfterDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_the_create_form_is_present_when_the_user_has_no_tokens(): void
    {
        $user = $this->user();
        $this->assertCount(0, $user->tokens);

        $this->actingAs($user)->get(route('api-tokens.index'))
            ->assertOk()
            ->assertSee('Create API Token');
    }

    public function test_the_create_form_survives_deleting_the_last_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('old-undocumented-token');

        $component = Livewire::actingAs($user)->test(ApiTokenManager::class);
        $component->assertSee('Create API Token');

        $component->call('confirmApiTokenDeletion', $token->accessToken->id)
            ->call('deleteApiToken')
            ->assertOk()
            ->assertSee('Create API Token');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_the_page_still_renders_the_form_after_the_last_token_is_gone(): void
    {
        $user = $this->user();
        $token = $user->createToken('old-undocumented-token');
        $token->accessToken->delete();

        $this->actingAs($user->fresh())->get(route('api-tokens.index'))
            ->assertOk()
            ->assertSee('Create API Token')
            ->assertSee('Token Name');
    }

    public function test_a_token_can_be_created_from_the_empty_state(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(ApiTokenManager::class)
            ->set('createApiTokenForm.name', 'mcp')
            ->call('createApiToken')
            ->assertHasNoErrors();

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertSame('mcp', $user->fresh()->tokens->first()->name);
    }

    public function test_the_nav_still_links_to_api_tokens_with_no_tokens(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('api-tokens.index'));
    }
}
