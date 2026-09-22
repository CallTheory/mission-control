<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let Mission Control, rather than an Intelligent Series Supervisor setting, decide which
 * provider a fax goes out through.
 *
 * Both columns are deliberately inert for existing installs: the seeded spool sources are
 * pinned to their own provider, so the default is never consulted for them, and failover
 * is off because it is new behaviour that no one has asked for yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Mirrors sms_default_provider, which does the same job for WCTP carriers.
            $table->string('fax_default_provider', 32)->nullable();

            // Off by default. Trying the other provider after a failed submission is a
            // behaviour change, and a site that has only ever used one provider should
            // not silently start using the other because of a transient error.
            $table->boolean('fax_failover_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['fax_default_provider', 'fax_failover_enabled']);
        });
    }
};
