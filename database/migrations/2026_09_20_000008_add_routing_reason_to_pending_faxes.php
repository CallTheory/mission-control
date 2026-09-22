<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why this fax went out through the provider it did.
 *
 * While the provider was the spool directory's name the answer was self-evident. Now that
 * Mission Control routes each fax — by pin, by the source's pinned provider, or by the
 * system default — "why did this one go through RingCentral" is a question the status
 * page has to be able to answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->string('routing_reason', 64)->nullable()->after('spool_source_key');
        });
    }

    public function down(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->dropColumn('routing_reason');
        });
    }
};
