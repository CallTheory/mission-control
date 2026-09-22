<?php

use App\Enums\FaxProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fax spool source is one place faxes arrive from: either Amtelco's Intelligent Series
 * Fax Service pushing into a Samba share on this host, or this host reaching out to an IS
 * server's own share.
 *
 * The spool used to be addressed as provider x folder, which assumed a single IS fax
 * service and made the provider a property of the directory. Sites run several IS servers
 * (only one processing at a time), and the provider needs to be a Mission Control
 * decision rather than a path.
 *
 * Seeding the two existing provider directories as sources keeps every current install
 * byte-identical: `mfax` resolves to storage/app/mfax and stays pinned to mFax. A site
 * adopts the flat, provider-agnostic layout by adding a source with no pinned provider,
 * when it chooses to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fax_spool_sources', function (Blueprint $table) {
            $table->id();

            // Also the lock and cache namespace, so it is constrained to a slug and is
            // never interpolated into a path without being resolved through this table.
            $table->string('key', 32)->unique();
            $table->string('name');
            $table->boolean('enabled')->default(true)->index();

            // 'local' reads a directory on this host (a Samba share we serve, or an
            // existing kernel CIFS mount). 'smb' reaches out to a remote share directly.
            $table->string('driver', 16)->default('local');

            // Null once the provider is chosen per fax. Set on the seeded rows so the
            // legacy folders keep meaning what they have always meant.
            $table->string('pinned_provider', 32)->nullable();

            // Null means storage_path("app/{key}"), which is what makes the seeded rows
            // resolve to today's directories without baking a deploy path into data.
            $table->string('root_path')->nullable();

            $table->timestamps();
        });

        $now = now();

        DB::table('fax_spool_sources')->insert(array_map(fn (FaxProvider $provider): array => [
            'key' => $provider->value,
            'name' => $provider->label(),
            'enabled' => true,
            'driver' => 'local',
            'pinned_provider' => $provider->value,
            'root_path' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], FaxProvider::cases()));
    }

    public function down(): void
    {
        Schema::dropIfExists('fax_spool_sources');
    }
};
