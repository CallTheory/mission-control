<?php

declare(strict_types=1);

namespace Tests\Feature\System\Wctp;

use App\Enums\Capability;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Who can reach the WCTP gateway section.
 *
 * The section used to be a per-team utility whose capability sat in the "open
 * utilities" set, which meant every seeded role -- agent and dispatcher included --
 * could open the host screen and create, edit or delete enterprise hosts. It is now
 * administrative: two capabilities held by the admin and technical roles only.
 */
class SectionAccessTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    /** Every page in the section, with the capability it needs. */
    private const PAGES = [
        'system.wctp' => null,
        'system.wctp.gateway' => Capability::WctpManage,
        'system.wctp.carriers' => Capability::WctpManage,
        'system.wctp.enterprise-hosts' => Capability::WctpManage,
        'system.wctp.messages' => Capability::WctpMessages,
    ];

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');
        $this->team = $this->createSeededTeam();
    }

    public static function administrativeRoles(): array
    {
        return ['admin' => ['admin'], 'technical' => ['technical']];
    }

    public static function standardRoles(): array
    {
        return [
            'manager' => ['manager'],
            'supervisor' => ['supervisor'],
            'dispatcher' => ['dispatcher'],
            'agent' => ['agent'],
        ];
    }

    #[DataProvider('administrativeRoles')]
    public function test_administrative_roles_can_open_every_page(string $role): void
    {
        $this->actingAs($this->createUserWithRole($this->team, $role));

        foreach (array_keys(self::PAGES) as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    #[DataProvider('standardRoles')]
    public function test_standard_roles_are_denied_every_page(string $role): void
    {
        $this->actingAs($this->createUserWithRole($this->team, $role));

        foreach (array_keys(self::PAGES) as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_a_role_with_only_the_message_capability_reads_the_log_but_cannot_configure(): void
    {
        // The point of splitting the two capabilities: handing out the log later is
        // a checkbox, and it does not hand over the setup screens with it.
        $user = $this->createUserWithout($this->team, 'technical', Capability::WctpManage);
        $this->actingAs($user);

        $this->get(route('system.wctp'))->assertOk();
        $this->get(route('system.wctp.messages'))->assertOk();

        $this->get(route('system.wctp.gateway'))->assertForbidden();
        $this->get(route('system.wctp.carriers'))->assertForbidden();
        $this->get(route('system.wctp.enterprise-hosts'))->assertForbidden();
    }

    public function test_a_role_with_only_the_manage_capability_cannot_read_the_log(): void
    {
        $user = $this->createUserWithout($this->team, 'technical', Capability::WctpMessages);
        $this->actingAs($user);

        $this->get(route('system.wctp.enterprise-hosts'))->assertOk();
        $this->get(route('system.wctp.messages'))->assertForbidden();
    }

    public function test_the_index_only_links_to_pages_the_viewer_can_open(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'technical', Capability::WctpManage));

        $response = $this->get(route('system.wctp'));

        $response->assertOk()
            ->assertSee(route('system.wctp.messages'))
            ->assertDontSee(route('system.wctp.carriers'))
            ->assertDontSee(route('system.wctp.enterprise-hosts'));
    }

    public function test_an_operator_without_system_access_gets_a_navigation_entry(): void
    {
        // The technical role holds the WCTP capabilities but not system.access, so it
        // sees no System tab and no System Settings dropdown -- without a top-level
        // entry there would be no way into the section at all.
        $technical = $this->createUserWithRole($this->team, 'technical');

        $this->assertFalse($technical->hasCapability(Capability::SystemAccess));

        $this->actingAs($technical)
            ->get(route('system.wctp.messages'))
            ->assertOk()
            // The main navigation entry, not the section's own sub-nav.
            ->assertSee('>'.e(__('WCTP Gateway')).'<', false);
    }

    public function test_the_whole_section_is_absent_when_the_system_feature_is_off(): void
    {
        $this->disableSystemFeature('wctp-gateway');
        $this->actingAs($this->createUserWithRole($this->team, 'admin'));

        foreach (array_keys(self::PAGES) as $name) {
            // 404 rather than 403: the feature is not switched on, so the page does
            // not exist rather than being withheld.
            $this->get(route($name))->assertNotFound();
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (array_keys(self::PAGES) as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('legacyUrls')]
    public function test_the_old_urls_still_resolve(string $old, string $new): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'admin'));

        $this->get($old)->assertRedirect($new);
    }

    public static function legacyUrls(): array
    {
        return [
            'system screen' => ['/system/wctp-gateway', '/system/wctp'],
            'utility gateway' => ['/utilities/wctp-gateway', '/system/wctp/gateway'],
            'utility hosts' => ['/utilities/enterprise-hosts', '/system/wctp/enterprise-hosts'],
            'utility messages' => ['/utilities/wctp-messages', '/system/wctp/messages'],
        ];
    }
}
