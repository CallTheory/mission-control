<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Enums\SmsProvider;
use App\Livewire\System\Wctp\EnterpriseHosts;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Enterprise host management, now a single administrative screen.
 *
 * The two screens this replaces were split by tenancy -- one team-scoped, one that
 * also showed global hosts -- so the tests that pinned "only the acting team's
 * hosts" are gone on purpose: hosts are installation-wide, and the gate is the
 * wctp.manage capability rather than a team's utility flag.
 */
class EnterpriseHostsTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');
        $this->team = $this->createSeededTeam();

        $this->actingAs($this->createUserWithRole($this->team, 'admin'));
    }

    private function host(array $attributes = []): EnterpriseHost
    {
        return EnterpriseHost::factory()->create($attributes);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_it_mounts_for_a_user_with_the_manage_capability(): void
    {
        Livewire::test(EnterpriseHosts::class)->assertSuccessful();
    }

    public function test_a_user_without_the_manage_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'agent'));

        Livewire::test(EnterpriseHosts::class)->assertForbidden();
    }

    // Every action on this screen also calls authorizeWctpSection(), because Livewire
    // does not re-run the controller's check on POST /livewire/update. It cannot be
    // asserted separately here -- the component needs the same capability to mount at
    // all -- but the equivalent guard is exercised on the message log, where retrying
    // needs a capability the screen itself does not.

    public function test_it_is_absent_when_the_system_feature_is_off(): void
    {
        $this->disableSystemFeature('wctp-gateway');

        Livewire::test(EnterpriseHosts::class)->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Listing
    // ---------------------------------------------------------------------

    public function test_it_lists_every_host_regardless_of_team(): void
    {
        $mine = $this->host(['team_id' => $this->team->id, 'name' => 'Mine']);
        $global = $this->host(['team_id' => null, 'name' => 'Global']);
        $anothers = $this->host(['team_id' => Team::factory()->create()->id, 'name' => 'Theirs']);

        Livewire::test(EnterpriseHosts::class)
            ->assertCanSeeTableRecords([$mine, $global, $anothers]);
    }

    public function test_search_matches_name_and_sender_id(): void
    {
        $alpha = $this->host(['name' => 'Alpha Enterprise']);
        $beta = $this->host(['name' => 'Beta Corporation']);
        $bySender = $this->host(['senderID' => 'alpha_sender']);

        Livewire::test(EnterpriseHosts::class)
            ->searchTable('alpha')
            ->assertCanSeeTableRecords([$alpha, $bySender])
            ->assertCanNotSeeTableRecords([$beta]);
    }

    public function test_the_enabled_filter_works(): void
    {
        $enabled = $this->host(['name' => 'Enabled Host', 'enabled' => true]);
        $disabled = $this->host(['name' => 'Disabled Host', 'enabled' => false]);

        Livewire::test(EnterpriseHosts::class)
            ->filterTable('enabled', true)
            ->assertCanSeeTableRecords([$enabled])
            ->assertCanNotSeeTableRecords([$disabled])
            ->filterTable('enabled', false)
            ->assertCanSeeTableRecords([$disabled])
            ->assertCanNotSeeTableRecords([$enabled]);
    }

    public function test_each_number_is_labelled_with_its_carrier(): void
    {
        $this->host([
            'name' => 'Mixed Carrier Host',
            'phone_numbers' => ['+15551112222', '+15553334444'],
            'number_providers' => ['15551112222' => 'bandwidth'],
        ]);

        Livewire::test(EnterpriseHosts::class)
            ->assertSee('+15551112222 · Bandwidth')
            // No carrier assigned, so it shows the default it will actually use.
            ->assertSee('+15553334444 · Twilio');
    }

    // ---------------------------------------------------------------------
    // Creating
    // ---------------------------------------------------------------------

    public function test_creating_a_host_encrypts_its_security_code(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Test Enterprise',
                'senderID' => 'test_sender',
                'securityCode' => 'secret123456',
                'enabled' => true,
            ])
            ->assertHasNoErrors();

        $host = EnterpriseHost::where('senderID', 'test_sender')->firstOrFail();

        // Accessor round-trips the plaintext...
        $this->assertSame('secret123456', $host->securityCode);
        // ...but the raw column must never be the plaintext.
        $this->assertNotSame(
            'secret123456',
            DB::table('enterprise_hosts')->where('id', $host->id)->value('securityCode'),
        );
    }

    public function test_creation_requires_a_name_sender_id_and_security_code(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [])
            ->assertHasFormErrors(['name', 'senderID', 'securityCode']);
    }

    public function test_the_sender_id_must_be_unique(): void
    {
        $this->host(['senderID' => 'existing_sender']);

        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Test Enterprise',
                'senderID' => 'existing_sender',
                'securityCode' => 'secret123456',
            ])
            ->assertHasFormErrors(['senderID']);
    }

    public function test_the_security_code_has_a_minimum_length(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Test Enterprise',
                'senderID' => 'test_sender',
                'securityCode' => 'short',
            ])
            ->assertHasFormErrors(['securityCode']);
    }

    public function test_the_callback_url_must_be_a_url(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Test Enterprise',
                'senderID' => 'test_sender',
                'securityCode' => 'secret123456',
                'callback_url' => 'invalid-url',
            ])
            ->assertHasFormErrors(['callback_url']);
    }

    public function test_phone_numbers_are_validated(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Test Enterprise',
                'senderID' => 'test_sender',
                'securityCode' => 'secret123456',
                'phone_numbers' => [
                    ['number' => 'invalid-phone', 'provider' => null],
                    ['number' => '+1234567890123456789', 'provider' => null],
                ],
            ])
            ->assertHasFormErrors(['phone_numbers.0.number', 'phone_numbers.1.number']);
    }

    public function test_numbers_are_saved_with_the_carrier_that_owns_them(): void
    {
        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('addHost', null, [
                'name' => 'Mixed Carrier Host',
                'senderID' => 'mixed123',
                'securityCode' => 'secret123456',
                'phone_numbers' => [
                    ['number' => '5551112222', 'provider' => 'bandwidth'],
                    ['number' => '+15553334444', 'provider' => 'commio'],
                    // No carrier chosen: falls back to the system default.
                    ['number' => '5555556666', 'provider' => null],
                ],
            ])
            ->assertHasNoFormErrors();

        $host = EnterpriseHost::where('senderID', 'mixed123')->firstOrFail();

        $this->assertSame(['+15551112222', '+15553334444', '+15555556666'], $host->phone_numbers);
        $this->assertSame(SmsProvider::Bandwidth, $host->providerForNumber('+1 (555) 111-2222'));
        $this->assertSame(SmsProvider::Commio, $host->providerForNumber('5553334444'));
        $this->assertNull($host->providerForNumber('5555556666'));
    }

    // ---------------------------------------------------------------------
    // Editing
    // ---------------------------------------------------------------------

    public function test_the_edit_dialog_opens_with_the_hosts_data_but_never_its_code(): void
    {
        $host = $this->host([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => 'original_secret',
            'callback_url' => 'https://example.com',
            'enabled' => false,
        ]);

        Livewire::test(EnterpriseHosts::class)
            ->mountTableAction('edit', $host)
            ->assertActionDataSet([
                'name' => 'Test Host',
                'senderID' => 'test123',
                // Never prefilled: the stored code must not reach the DOM.
                'securityCode' => '',
                'callback_url' => 'https://example.com',
                'enabled' => false,
            ]);
    }

    public function test_updating_a_host(): void
    {
        $host = $this->host(['name' => 'Original Name', 'securityCode' => 'original_secret']);

        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('edit', $host, [
                'name' => 'Updated Name',
                'senderID' => $host->senderID,
                'enabled' => false,
                'securityCode' => 'new_security_code_123',
            ])
            ->assertHasNoErrors();

        $host->refresh();

        $this->assertSame('Updated Name', $host->name);
        $this->assertFalse($host->enabled);
        $this->assertSame('new_security_code_123', $host->securityCode);
    }

    public function test_leaving_the_security_code_blank_on_edit_keeps_the_stored_one(): void
    {
        $host = $this->host(['securityCode' => 'original_secret']);

        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('edit', $host, [
                'name' => 'Renamed',
                'senderID' => $host->senderID,
                'securityCode' => '',
            ])
            ->assertHasNoErrors();

        $host->refresh();

        $this->assertSame('Renamed', $host->name);
        $this->assertSame('original_secret', $host->securityCode);
    }

    public function test_editing_a_host_round_trips_its_number_carriers(): void
    {
        $host = $this->host([
            'phone_numbers' => ['+15551112222'],
            'number_providers' => ['15551112222' => 'bandwidth'],
        ]);

        Livewire::test(EnterpriseHosts::class)
            ->mountTableAction('edit', $host)
            ->assertActionDataSet(fn (array $data): bool => array_values(array_map(
                fn (array $row): array => ['number' => $row['number'], 'provider' => $row['provider']],
                $data['phone_numbers'],
            )) === [
                ['number' => '+15551112222', 'provider' => 'bandwidth'],
            ]);
    }

    public function test_clearing_a_numbers_carrier_returns_it_to_the_default(): void
    {
        $host = $this->host([
            'phone_numbers' => ['+15551112222'],
            'number_providers' => ['15551112222' => 'bandwidth'],
        ]);

        Livewire::test(EnterpriseHosts::class)
            ->callTableAction('edit', $host, [
                'name' => $host->name,
                'senderID' => $host->senderID,
                'securityCode' => '',
                'phone_numbers' => [
                    ['number' => '+15551112222', 'provider' => null],
                ],
            ])
            ->assertHasNoFormErrors();

        $host->refresh();

        $this->assertSame(['+15551112222'], $host->phone_numbers);
        $this->assertSame([], $host->number_providers);
        $this->assertNull($host->providerForNumber('+15551112222'));
    }

    // ---------------------------------------------------------------------
    // Enabling and deleting
    // ---------------------------------------------------------------------

    public function test_toggling_a_hosts_enabled_state(): void
    {
        $host = $this->host(['enabled' => true]);

        Livewire::test(EnterpriseHosts::class)->callTableAction('toggleEnabled', $host);
        $this->assertFalse($host->refresh()->enabled);

        Livewire::test(EnterpriseHosts::class)->callTableAction('toggleEnabled', $host);
        $this->assertTrue($host->refresh()->enabled);
    }

    public function test_deleting_a_host_with_no_messages(): void
    {
        $host = $this->host(['name' => 'Deletable Host']);

        Livewire::test(EnterpriseHosts::class)->callTableAction('delete', $host);

        $this->assertDatabaseMissing('enterprise_hosts', ['id' => $host->id]);
    }

    public function test_a_host_with_messages_offers_no_delete(): void
    {
        $host = $this->host();
        WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);

        // Disabled, never deleted: its traffic is the record of what it sent.
        Livewire::test(EnterpriseHosts::class)
            ->assertTableActionHidden('delete', $host)
            ->assertTableActionVisible('toggleEnabled', $host);

        $this->assertDatabaseHas('enterprise_hosts', ['id' => $host->id]);
    }

    public function test_the_messages_action_is_hidden_without_the_message_capability(): void
    {
        $host = $this->host();

        Livewire::test(EnterpriseHosts::class)->assertTableActionVisible('messages', $host);

        $this->actingAs($this->createUserWithout($this->team, 'admin', Capability::WctpMessages));

        Livewire::test(EnterpriseHosts::class)->assertTableActionHidden('messages', $host);
    }
}
