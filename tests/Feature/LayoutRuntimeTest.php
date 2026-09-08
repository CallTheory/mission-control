<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * Filament's runtime pieces all fail silently: without @livewire('notifications') a
 * toast is dispatched and never drawn, and without @filamentStyles a schema renders
 * as unstyled inputs. The page still returns 200 either way, which is how the
 * notifications component came to be missing from both layouts for two phases while
 * every Notification::make()->send() call looked correct.
 *
 * DesignSystemTest checks the directives are present in the Blade source; this checks
 * they actually reach the browser.
 */
class LayoutRuntimeTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createUserWithRole($this->createSeededTeam(), 'admin');
    }

    public function test_an_authenticated_page_renders_the_toast_container(): void
    {
        $response = $this->actingAs($this->admin())->get('/system/integrations');

        $response->assertOk();
        $response->assertSee('wire:name="notifications"', escape: false);
    }

    public function test_an_authenticated_page_loads_the_filament_stylesheet(): void
    {
        $response = $this->actingAs($this->admin())->get('/system/integrations');

        // @filamentStyles emits the colour variables Filament's CSS maps through.
        $response->assertSee('--primary-500', escape: false);
    }

    public function test_a_guest_page_renders_the_toast_container(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('wire:name="notifications"', escape: false);
    }
}
