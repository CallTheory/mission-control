<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Stats\Calls\Call;
use App\Models\Team;
use App\Models\User;
use App\Support\CallAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use stdClass;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class RecordingAccessTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private function userOnTeam(array $teamAttributes = []): User
    {
        $team = Team::factory()->create(array_merge(
            ['personal_team' => false, 'unrestricted_accounts' => false],
            $teamAttributes,
        ));
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user;
    }

    public function test_unconfigured_team_is_denied_recording(): void
    {
        // No allow-lists and not marked unrestricted: unconfigured. Must 403 before
        // the (switch-DB) call lookup is ever attempted.
        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $this->actingAs($user)
            ->get('/utilities/recording/12345.wav')
            ->assertForbidden();
    }

    public function test_unconfigured_team_is_denied_screencapture(): void
    {
        $this->disableSystemFeature('screencaptures');
        // screencapture feature must be enabled to get past the 404 gate.
        $this->enableSystemFeature('screencaptures');

        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $this->actingAs($user)
            ->get('/utilities/screencapture/12345.mp4')
            ->assertForbidden();

    }

    public function test_a_team_marked_unrestricted_is_allowed_through(): void
    {
        // Marked unrestricted, so the guard no longer 403s; the request proceeds to the
        // call lookup instead (which, lacking a switch DB in tests, 400s). The point is
        // simply that the response is NOT 403.
        $user = $this->userOnTeam([
            'allowed_accounts' => null,
            'allowed_billing' => null,
            'unrestricted_accounts' => true,
        ]);

        $response = $this->actingAs($user)->get('/utilities/recording/12345.wav');

        $this->assertNotSame(403, $response->getStatusCode());
    }

    private function userOnPersonalTeam(?string $agtId): User
    {
        $team = Team::factory()->create(['personal_team' => true, 'unrestricted_accounts' => false]);
        $user = User::factory()->create(['agtId' => $agtId]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user;
    }

    /**
     * A personal team has no allow-lists and never will -- CallLookupController scopes it
     * by agent instead. The media endpoints used to have no agent path at all, so they hit
     * the fail-closed guard and 403'd every recording on a page the same user could open.
     */
    public function test_personal_team_is_not_denied_media_by_the_unconfigured_guard(): void
    {
        $user = $this->userOnPersonalTeam('4321');

        $response = $this->actingAs($user)->get('/utilities/recording/12345.wav');

        // Without a switch DB the call lookup 400s; the point is that it got that far
        // rather than being turned away by the team guard.
        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function test_personal_team_without_an_agent_id_is_still_denied(): void
    {
        $user = $this->userOnPersonalTeam(null);

        $this->actingAs($user)
            ->get('/utilities/recording/12345.wav')
            ->assertForbidden();
    }

    /**
     * The half of the rule that needs the call itself, exercised directly because a full
     * request needs the Intelligent Series switch database to resolve a call.
     */
    public function test_personal_team_reaches_only_its_own_calls(): void
    {
        $user = $this->userOnPersonalTeam('4321');

        $own = $this->fakeCall(agtId: '4321');
        $someoneElses = $this->fakeCall(agtId: '9999');

        $this->assertTrue(CallAccess::allows($user, $own));
        $this->assertFalse(CallAccess::allows($user, $someoneElses));
    }

    public function test_shared_team_is_still_scoped_by_its_allow_lists(): void
    {
        $user = $this->userOnTeam(['allowed_accounts' => '1000,2000', 'allowed_billing' => null]);

        $this->assertTrue(CallAccess::allows($user, $this->fakeCall(clientNumber: '2000')));
        $this->assertFalse(CallAccess::allows($user, $this->fakeCall(clientNumber: '3000')));
    }

    /**
     * A shared team with no lists and no explicit decision stays fail-closed: it could
     * otherwise walk call ids for anyone's audio. Ticking the box is the decision.
     */
    public function test_unconfigured_shared_team_is_denied_even_with_a_matching_call(): void
    {
        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $this->assertFalse(CallAccess::allows($user, $this->fakeCall(clientNumber: '2000')));

        // Not mass assignable, like the allow-lists beside it -- the settings forms
        // assign it directly, so the test does too.
        $user->currentTeam->unrestricted_accounts = true;
        $user->currentTeam->save();

        $this->assertTrue(CallAccess::allows($user, $this->fakeCall(clientNumber: '2000')));
    }

    /**
     * Call hits the switch DB in its constructor, so stand in a bare instance carrying
     * only the fields the access rule reads.
     */
    private function fakeCall(string $clientNumber = '1000', string $billingCode = '', string $agtId = '1'): Call
    {
        $call = (new ReflectionClass(Call::class))->newInstanceWithoutConstructor();

        $row = new stdClass;
        $row->ClientNumber = $clientNumber;
        $row->BillingCode = $billingCode;
        $row->agtId = $agtId;

        $call->results = [$row];

        return $call;
    }
}
