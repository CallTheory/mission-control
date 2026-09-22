<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Enums\FaxProvider;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaxSpoolSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_seeds_one_source_per_existing_provider_directory(): void
    {
        $sources = FaxSpoolSource::query()->orderBy('key')->get();

        $this->assertSame(['mfax', 'ringcentral'], $sources->pluck('key')->all());

        foreach ($sources as $source) {
            $this->assertTrue($source->enabled);
            $this->assertSame(FaxSpoolSource::DRIVER_LOCAL, $source->driver);
            $this->assertSame($source->key, $source->pinned_provider->value);
        }
    }

    public function test_a_seeded_source_resolves_to_todays_spool_directory(): void
    {
        // This is the whole backward-compatibility promise: {root}/{folder} for the
        // seeded rows has to be the path the spool has always used.
        $this->assertSame(
            storage_path('app/mfax'),
            FaxSpoolSource::findByKey('mfax')->rootPath()
        );

        $this->assertSame(
            storage_path('app/ringcentral'),
            FaxSpoolSource::findByKey('ringcentral')->rootPath()
        );
    }

    public function test_root_path_is_not_baked_into_the_seeded_rows(): void
    {
        // Storing a resolved storage_path() would pin the source to whatever deploy
        // directory the migration happened to run in.
        $this->assertNull(FaxSpoolSource::findByKey('mfax')->root_path);
    }

    public function test_an_explicit_root_path_overrides_the_convention(): void
    {
        $source = FaxSpoolSource::create([
            'key' => 'is2',
            'name' => 'IS 2',
            'driver' => FaxSpoolSource::DRIVER_LOCAL,
            'root_path' => '/mnt/is2/fax/',
        ]);

        // Trailing separator is normalised away so callers can append unconditionally.
        $this->assertSame('/mnt/is2/fax', $source->rootPath());
    }

    public function test_only_provider_named_sources_are_legacy(): void
    {
        $this->assertTrue(FaxSpoolSource::findByKey('mfax')->isLegacy());

        $flat = FaxSpoolSource::create(['key' => 'primary', 'name' => 'Primary']);
        $this->assertFalse($flat->isLegacy());

        // A new source may still be pinned to a provider without being legacy; what makes
        // it legacy is being *named* after the provider, i.e. having been a directory.
        $pinned = FaxSpoolSource::create([
            'key' => 'is2',
            'name' => 'IS 2',
            'pinned_provider' => FaxProvider::Mfax,
        ]);
        $this->assertFalse($pinned->isLegacy());
    }

    public function test_the_enabled_scope_excludes_disabled_sources(): void
    {
        FaxSpoolSource::findByKey('ringcentral')->update(['enabled' => false]);

        $this->assertSame(['mfax'], FaxSpoolSource::query()->enabled()->pluck('key')->all());
    }

    public function test_pending_faxes_carries_a_spool_source(): void
    {
        $this->assertTrue(Schema::hasColumn('pending_faxes', 'spool_source_key'));
    }

    public function test_existing_pending_rows_backfill_their_source_from_the_provider(): void
    {
        // Reproduces the migration's backfill expression against a row written before the
        // column existed, so the statement is proven portable rather than assumed.
        $fax = PendingFax::create([
            'api_fax_id' => 'abc123',
            'fax_provider' => 'ringcentral',
            'spool_source_key' => null,
            'job_id' => 4242,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'original_status' => '2',
        ]);

        DB::table('pending_faxes')
            ->whereNull('spool_source_key')
            ->update(['spool_source_key' => DB::raw('fax_provider')]);

        $this->assertSame('ringcentral', $fax->fresh()->spool_source_key);
    }
}
