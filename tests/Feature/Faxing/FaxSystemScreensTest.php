<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Livewire\System\FaxProviderPins;
use App\Livewire\System\FaxSpoolSources;
use App\Models\DataSource;
use App\Models\FaxProviderPin;
use App\Models\FaxSpoolSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

class FaxSystemScreensTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        $this->enableSystemFeature('cloud-faxing');
        DataSource::create(['mfax_api_key' => encrypt('test-api-key')]);

        // Both screens are gated by AuthorizesSystemComponent.
        $this->actingAs($this->createUserWithRole($this->createSeededTeam(), 'admin'));
    }

    public function test_the_fax_servers_screen_lists_the_seeded_sources(): void
    {
        Livewire::test(FaxSpoolSources::class)
            ->assertSuccessful()
            ->assertSee('mFax')
            ->assertSee('RingCentral');
    }

    public function test_a_new_fax_server_can_be_added(): void
    {
        Livewire::test(FaxSpoolSources::class)
            ->callTableAction('createSource', data: [
                'name' => 'IS Building B',
                'key' => 'is-b',
                'driver' => FaxSpoolSource::DRIVER_LOCAL,
                'enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        $source = FaxSpoolSource::findByKey('is-b');

        $this->assertNotNull($source);
        // Unpinned, so the provider is chosen per fax rather than by the directory.
        $this->assertNull($source->pinned_provider);

        // Amtelco writes into these over Samba, so a share pointing at a path that does
        // not exist would fail in a way that reads as a fax problem, not a setup one.
        foreach (['tosend', 'sent', 'fail', 'preproc'] as $folder) {
            $this->assertDirectoryExists($source->rootPath()."/{$folder}");
        }

        File::deleteDirectory($source->rootPath());
    }

    public function test_a_remote_servers_folders_are_not_ours_to_create(): void
    {
        Livewire::test(FaxSpoolSources::class)
            ->callTableAction('createSource', data: [
                'name' => 'IS Remote',
                'key' => 'is-remote',
                'driver' => FaxSpoolSource::DRIVER_SMB,
                'smb_host' => '10.0.0.5',
                'smb_username' => 'svcfax',
                'smb_password' => 'secret',
                'enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        $source = FaxSpoolSource::findByKey('is-remote');

        $this->assertSame([], $source->ensureFolders());
        $this->assertDirectoryDoesNotExist(storage_path('app/is-remote'));
    }

    public function test_a_legacy_source_cannot_be_deleted(): void
    {
        // Every pre-existing install depends on these two, and their keys are referenced
        // by pending faxes and by queue lock names.
        Livewire::test(FaxSpoolSources::class)
            ->assertTableActionHidden('deleteSource', FaxSpoolSource::findByKey('mfax'));
    }

    public function test_testing_a_connection_reports_back(): void
    {
        FaxSpoolSource::create([
            'key' => 'gone',
            'name' => 'Missing Server',
            'root_path' => '/nonexistent/spool',
        ]);

        Livewire::test(FaxSpoolSources::class)
            ->callTableAction('testConnection', FaxSpoolSource::findByKey('gone'))
            ->assertNotified();
    }

    public function test_the_routing_screen_saves_the_default_provider(): void
    {
        Livewire::test(FaxProviderPins::class)
            ->callTableAction('routingSettings', data: [
                'fax_default_provider' => 'ringcentral',
                'fax_failover_enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        $datasource = DataSource::first();

        // The headline capability: the provider changed without touching Intelligent Series.
        $this->assertSame('ringcentral', $datasource->fax_default_provider);
        $this->assertTrue((bool) $datasource->fax_failover_enabled);
    }

    public function test_a_pin_normalizes_the_number_it_was_given(): void
    {
        Livewire::test(FaxProviderPins::class)
            ->callTableAction('createPin', data: [
                'match_type' => FaxProviderPin::MATCH_NUMBER,
                'match_value' => '+1 (913) 906-9098',
                'provider' => 'ringcentral',
                'allow_failover' => false,
                'enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        // However it is typed here, it has to match the number the .fs file carries.
        $this->assertSame('19139069098', FaxProviderPin::first()->match_value);
    }
}
