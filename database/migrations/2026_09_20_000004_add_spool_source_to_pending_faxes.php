<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which spool source each fax came from.
 *
 * `.fs` filenames are short per-server Intelligent Series sequences (IS20.fs), so they are
 * unique only within one source. Without this column two sources collide on the dedupe
 * that decides whether a fax has already been submitted, and one server's fax is silently
 * never sent.
 *
 * Existing rows predate multiple sources and came from the provider-named directories, so
 * the provider *is* the source key for them — which is exactly what the seeded
 * fax_spool_sources rows are keyed on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->string('spool_source_key', 32)->nullable()->after('fax_provider');
        });

        DB::table('pending_faxes')
            ->whereNull('spool_source_key')
            ->update(['spool_source_key' => DB::raw('fax_provider')]);

        Schema::table('pending_faxes', function (Blueprint $table) {
            // The lane a scan works through. The existing
            // ['delivery_status','fax_provider'] index is deliberately left in place:
            // isfax:check-pending still polls across every source by status and provider.
            $table->index(['spool_source_key', 'fax_provider', 'delivery_status'], 'pending_faxes_lane_index');
            $table->index(['fs_file_name', 'spool_source_key'], 'pending_faxes_fs_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->dropIndex('pending_faxes_fs_source_index');
            $table->dropIndex('pending_faxes_lane_index');
            $table->dropColumn('spool_source_key');
        });
    }
};
