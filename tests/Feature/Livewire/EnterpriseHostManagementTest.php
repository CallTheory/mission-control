<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Utilities\EnterpriseHostManagement;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\User;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EnterpriseHostManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['session']->start();
        $this->enableWctpFeature();

        $this->team = $this->makeWctpTeam();
        $this->user = User::factory()->create();
        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Storage::delete('feature-flags/wctp-gateway.flag');

        parent::tearDown();
    }

    private function enableWctpFeature(): void
    {
        Storage::makeDirectory('feature-flags');
        Storage::put('feature-flags/wctp-gateway.flag', encrypt('wctp-gateway'));
    }

    private function makeWctpTeam(): Team
    {
        $team = Team::factory()->create(['personal_team' => false]);
        // utility_wctp_gateway is not mass-assignable on the Team model.
        $team->forceFill(['utility_wctp_gateway' => true])->save();

        return $team->refresh();
    }

    private function host(array $attributes = []): EnterpriseHost
    {
        return EnterpriseHost::factory()->create(array_merge(
            ['team_id' => $this->team->id],
            $attributes
        ));
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_component_mounts_for_authorized_team(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->assertSuccessful();
    }

    public function test_personal_team_is_forbidden(): void
    {
        $personal = Team::factory()->create(['personal_team' => true]);
        $this->user->teams()->attach($personal, ['role' => 'admin']);
        // Reload so the freshly attached team is visible to belongsToTeam()/switchTeam().
        $this->user->refresh();
        $this->user->switchTeam($personal);

        $this->get(route('utilities.enterprise-hosts'))->assertForbidden();
    }

    public function test_team_without_utility_flag_is_forbidden(): void
    {
        $this->team->forceFill(['utility_wctp_gateway' => false])->save();

        $this->get(route('utilities.enterprise-hosts'))->assertForbidden();
    }

    public function test_disabled_system_feature_is_forbidden(): void
    {
        Storage::delete('feature-flags/wctp-gateway.flag');

        $this->get(route('utilities.enterprise-hosts'))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Tenant isolation
    // ---------------------------------------------------------------------

    public function test_only_current_team_hosts_are_listed(): void
    {
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $mine = $this->host(['name' => 'My Host']);
        $theirs = EnterpriseHost::factory()->create(['name' => 'Their Host', 'team_id' => $otherTeam->id]);

        Livewire::test(EnterpriseHostManagement::class)
            ->assertViewHas('hosts', function ($hosts) use ($mine, $theirs) {
                $ids = $hosts->pluck('id')->all();

                return in_array($mine->id, $ids, true)
                    && ! in_array($theirs->id, $ids, true);
            })
            ->assertSee('My Host')
            ->assertDontSee('Their Host');
    }

    public function test_cannot_edit_another_teams_host(): void
    {
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $theirs = EnterpriseHost::factory()->create(['team_id' => $otherTeam->id]);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('editHost', $theirs)
            ->assertForbidden();
    }

    public function test_cannot_delete_another_teams_host(): void
    {
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $theirs = EnterpriseHost::factory()->create(['team_id' => $otherTeam->id]);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('deleteHost', $theirs)
            ->assertForbidden();

        $this->assertDatabaseHas('enterprise_hosts', ['id' => $theirs->id]);
    }

    public function test_cannot_toggle_another_teams_host(): void
    {
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $theirs = EnterpriseHost::factory()->create(['team_id' => $otherTeam->id, 'enabled' => true]);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('toggleEnabled', $theirs)
            ->assertForbidden();

        $this->assertTrue($theirs->refresh()->enabled);
    }

    public function test_created_host_is_owned_by_current_team(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Enterprise')
            ->set('senderID', 'test_sender')
            ->set('securityCode', 'secret123456')
            ->set('enabled', true)
            // Attempt to plant the host in another team — must be ignored.
            ->set('team_id', Team::factory()->create()->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $host = EnterpriseHost::where('senderID', 'test_sender')->firstOrFail();
        $this->assertSame($this->team->id, $host->team_id);
    }

    // ---------------------------------------------------------------------
    // Behaviour (scoped to the acting team)
    // ---------------------------------------------------------------------

    public function test_displays_hosts_with_pagination(): void
    {
        $hosts = collect(range(1, 15))->map(fn () => $this->host());
        $firstByName = $hosts->sortBy('name')->first();

        Livewire::test(EnterpriseHostManagement::class)
            ->assertSee($firstByName->name)
            ->assertSee('Next');
    }

    public function test_search_functionality(): void
    {
        $this->host(['name' => 'Alpha Enterprise']);
        $this->host(['name' => 'Beta Corporation']);
        $this->host(['senderID' => 'alpha_sender']);

        Livewire::test(EnterpriseHostManagement::class)
            ->set('search', 'alpha')
            ->assertSee('Alpha Enterprise')
            ->assertSee('alpha_sender')
            ->assertDontSee('Beta Corporation');
    }

    public function test_enabled_filter(): void
    {
        $this->host(['name' => 'Enabled Host', 'enabled' => true]);
        $this->host(['name' => 'Disabled Host', 'enabled' => false]);

        Livewire::test(EnterpriseHostManagement::class)
            ->set('filterEnabled', '1')
            ->assertSee('Enabled Host')
            ->assertDontSee('Disabled Host')
            ->set('filterEnabled', '0')
            ->assertSee('Disabled Host')
            ->assertDontSee('Enabled Host');
    }

    public function test_successful_host_creation_encrypts_security_code(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Enterprise')
            ->set('senderID', 'test_sender')
            ->set('securityCode', 'secret123456')
            ->set('enabled', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('enterprise_hosts', [
            'name' => 'Test Enterprise',
            'senderID' => 'test_sender',
            'enabled' => true,
        ]);

        $host = EnterpriseHost::where('senderID', 'test_sender')->firstOrFail();
        // Accessor round-trips the plaintext...
        $this->assertEquals('secret123456', $host->securityCode);
        // ...but the raw column must never be the plaintext.
        $raw = \DB::table('enterprise_hosts')->where('id', $host->id)->value('securityCode');
        $this->assertNotEquals('secret123456', $raw);
    }

    public function test_host_creation_validation(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->call('save')
            ->assertHasErrors([
                'name' => 'required',
                'senderID' => 'required',
                'securityCode' => 'required',
            ]);
    }

    public function test_unique_sender_id_validation(): void
    {
        $this->host(['senderID' => 'existing_sender']);

        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Enterprise')
            ->set('senderID', 'existing_sender')
            ->set('securityCode', 'secret123456')
            ->call('save')
            ->assertHasErrors(['senderID' => 'unique']);
    }

    public function test_security_code_minimum_length_validation(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Enterprise')
            ->set('senderID', 'test_sender')
            ->set('securityCode', 'short')
            ->call('save')
            ->assertHasErrors(['securityCode' => 'min']);
    }

    public function test_edit_host_modal_opens_with_data(): void
    {
        $host = $this->host([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => 'original_secret',
            'callback_url' => 'https://example.com',
            'enabled' => false,
        ]);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('editHost', $host)
            ->assertSet('showModal', true)
            ->assertSet('name', 'Test Host')
            ->assertSet('senderID', 'test123')
            ->assertSet('securityCode', '')
            ->assertSet('callback_url', 'https://example.com')
            ->assertSet('enabled', false);
    }

    public function test_successful_host_update(): void
    {
        $host = $this->host(['name' => 'Original Name', 'securityCode' => 'original_secret']);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('editHost', $host)
            ->set('name', 'Updated Name')
            ->set('enabled', false)
            ->set('securityCode', 'new_security_code_123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $host->refresh();
        $this->assertEquals('Updated Name', $host->name);
        $this->assertFalse($host->enabled);
        $this->assertEquals('new_security_code_123', $host->securityCode);
        $this->assertSame($this->team->id, $host->team_id);
    }

    public function test_delete_host_without_messages(): void
    {
        $host = $this->host(['name' => 'Deletable Host']);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('deleteHost', $host);

        $this->assertDatabaseMissing('enterprise_hosts', ['id' => $host->id]);
    }

    public function test_delete_host_with_messages_shows_error(): void
    {
        $host = $this->host();
        WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);

        Livewire::test(EnterpriseHostManagement::class)
            ->call('deleteHost', $host);

        $this->assertDatabaseHas('enterprise_hosts', ['id' => $host->id]);
    }

    public function test_toggle_enabled_status(): void
    {
        $host = $this->host(['enabled' => true]);

        Livewire::test(EnterpriseHostManagement::class)->call('toggleEnabled', $host);
        $this->assertFalse($host->refresh()->enabled);

        Livewire::test(EnterpriseHostManagement::class)->call('toggleEnabled', $host);
        $this->assertTrue($host->refresh()->enabled);
    }

    public function test_generate_security_code(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->call('generateSecurityCode')
            ->assertSet('securityCode', fn ($value) => strlen($value) === 16);
    }

    public function test_view_messages_redirect(): void
    {
        $host = $this->host();

        Livewire::test(EnterpriseHostManagement::class)
            ->call('viewMessages', $host)
            ->assertRedirect(route('utilities.wctp-messages', ['host' => $host->id]));
    }

    public function test_callback_url_validation(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Host')
            ->set('senderID', 'test123')
            ->set('securityCode', 'secret123456')
            ->set('callback_url', 'invalid-url')
            ->call('save')
            ->assertHasErrors(['callback_url' => 'url']);
    }

    public function test_phone_numbers_validation(): void
    {
        Livewire::test(EnterpriseHostManagement::class)
            ->set('name', 'Test Host')
            ->set('senderID', 'test123')
            ->set('securityCode', 'secret123456')
            ->set('phoneNumbers', ['invalid-phone', '+1234567890123456789'])
            ->call('save')
            ->assertHasErrors(['phoneNumbers.0', 'phoneNumbers.1']);
    }
}
