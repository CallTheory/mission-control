<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Livewire\Utilities\CloudFaxingRingCentral;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deleting spool files from the UI is destructive and irreversible, so the gate is the
 * point of these tests: Filament's visible() only decides whether a button is drawn, and a
 * Livewire action can be invoked by anyone who can reach POST /livewire/update.
 */
class FaxSpoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $dirs = ['tosend', 'sent', 'fail', 'preproc'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'ringcentral_client_id' => 'test-client-id',
            'ringcentral_client_secret' => encrypt('secret'),
            'ringcentral_jwt_token' => encrypt('jwt'),
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
        ]);

        foreach ($this->dirs as $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");

            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }

            foreach (array_diff(scandir($path), ['.', '..', '.gitignore']) as $file) {
                @unlink($path.$file);
            }
        }

        // The page reads its listing from the shared snapshot; nothing cached is fine.
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('setEx')->andReturnTrue();
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");

            foreach (array_diff(scandir($path), ['.', '..', '.gitignore']) as $file) {
                @unlink($path.$file);
            }
        }

        parent::tearDown();
    }

    public function test_a_technical_user_can_delete_a_spool_file(): void
    {
        $this->actingAs($this->member('technical'));

        $path = storage_path('app/ringcentral/fail/IS30.cap');
        file_put_contents($path, 'x');

        Livewire::test(CloudFaxingRingCentral::class)
            ->callAction('deleteSpoolFile', arguments: ['folder' => 'fail', 'file' => 'IS30.cap'])
            ->assertHasNoErrors();

        $this->assertFileDoesNotExist($path);
    }

    public function test_an_admin_can_clear_a_folder(): void
    {
        $this->actingAs($this->member('admin'));

        foreach (['IS31.cap', 'IS31.fs'] as $name) {
            file_put_contents(storage_path("app/ringcentral/fail/{$name}"), 'x');
        }

        Livewire::test(CloudFaxingRingCentral::class)
            ->callAction('clearSpoolFolder', arguments: ['folder' => 'fail']);

        $this->assertFileDoesNotExist(storage_path('app/ringcentral/fail/IS31.cap'));
        $this->assertFileDoesNotExist(storage_path('app/ringcentral/fail/IS31.fs'));
    }

    /**
     * A supervisor can read the fax page — that is routine work — but must not be able to
     * delete out of the spool.
     */
    public function test_a_supervisor_cannot_delete_a_spool_file(): void
    {
        $supervisor = $this->member('supervisor');
        $this->actingAs($supervisor);

        $this->assertFalse($supervisor->hasCapability(Capability::FaxManageSpool));

        $path = storage_path('app/ringcentral/fail/IS32.cap');
        file_put_contents($path, 'x');

        // Driving the Livewire methods directly rather than through callAction(), which
        // asserts the button is visible before calling — the thing being tested here is
        // what happens when somebody skips the button entirely.
        Livewire::test(CloudFaxingRingCentral::class)
            ->assertDontSee('Clear Folder')
            ->call('mountAction', 'deleteSpoolFile', ['folder' => 'fail', 'file' => 'IS32.cap'])
            ->call('callMountedAction');

        $this->assertFileExists($path);
    }

    public function test_a_supervisor_cannot_clear_a_folder(): void
    {
        $this->actingAs($this->member('supervisor'));

        $path = storage_path('app/ringcentral/fail/IS34.cap');
        file_put_contents($path, 'x');

        Livewire::test(CloudFaxingRingCentral::class)
            ->call('mountAction', 'clearSpoolFolder', ['folder' => 'fail'])
            ->call('callMountedAction');

        $this->assertFileExists($path);
    }

    public function test_the_delete_controls_are_hidden_without_the_capability(): void
    {
        $this->actingAs($this->member('agent'));

        file_put_contents(storage_path('app/ringcentral/fail/IS33.cap'), 'x');

        Livewire::test(CloudFaxingRingCentral::class)
            ->assertSet('state.files_in_fail_count', 0)
            ->assertDontSee('Clear Folder');
    }

    private function member(string $roleKey): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        (new SeedDefaultRolesForTeam)($team);

        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $roleKey]);
        $user->switchTeam($team);
        $user->assignRole($team->roles()->where('key', $roleKey)->firstOrFail());

        return $user->fresh();
    }
}
