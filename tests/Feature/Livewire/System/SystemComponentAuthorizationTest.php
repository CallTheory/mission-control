<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\System\Integrations\Twilio;
use App\Models\DataSource;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class SystemComponentAuthorizationTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    /**
     * The regression guard that matters most: a System component added later
     * without a capability declaration fails here rather than shipping ungated.
     */
    public function test_every_system_livewire_component_declares_a_capability(): void
    {
        $missing = [];

        foreach (Finder::create()->files()->in(app_path('Livewire/System'))->name('*.php') as $file) {
            /** @var SplFileInfo $file */
            $class = 'App\\Livewire\\System\\'.str_replace(
                ['/', '.php'], ['\\', ''], $file->getRelativePathname()
            );

            if (! class_exists($class)) {
                continue;
            }

            if (! in_array(AuthorizesSystemComponent::class, class_uses_recursive($class), true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'System Livewire components missing AuthorizesSystemComponent: '
            .implode(', ', $missing));
    }

    public function test_holder_of_the_capability_can_mount_and_save(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');

        Livewire::actingAs($admin)
            ->test(Twilio::class)
            ->set('state.twilio_account_sid', 'AC-new')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('AC-new', DataSource::first()->twilio_account_sid);
    }

    public function test_a_user_without_the_capability_cannot_write(): void
    {
        DataSource::create(['twilio_account_sid' => 'AC-original']);

        $user = $this->createUserWithout($this->team, 'agent', Capability::SystemIntegrations);

        try {
            Livewire::actingAs($user)
                ->test(Twilio::class)
                ->set('state.twilio_account_sid', 'AC-hacked')
                ->call('save');
        } catch (\Throwable) {
            // Livewire renders the authorization failure rather than rethrowing
            // it cleanly out of mount(); the security property is the write.
        }

        $this->assertSame('AC-original', DataSource::first()->twilio_account_sid);
    }

    public function test_revoking_the_capability_blocks_writes_mid_session(): void
    {
        // Livewire does not re-run the page controller's authorize() on
        // POST /livewire/update, so without the trait a user who loaded the page
        // could keep saving after losing the capability.
        DataSource::create(['twilio_account_sid' => 'AC-original']);

        $admin = $this->createUserWithRole($this->team, 'admin');
        $component = Livewire::actingAs($admin)->test(Twilio::class);

        $role = $this->team->roles()->where('key', 'admin')->firstOrFail();
        $role->capabilities()->where('capability', Capability::SystemIntegrations->value)->delete();
        auth()->setUser($admin->fresh());

        try {
            $component->set('state.twilio_account_sid', 'AC-hacked')->call('save');
        } catch (\Throwable) {
            // as above
        }

        $this->assertSame('AC-original', DataSource::first()->twilio_account_sid);
    }
}
