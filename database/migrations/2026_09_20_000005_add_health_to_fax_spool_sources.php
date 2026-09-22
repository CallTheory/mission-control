<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether each spool source is answering.
 *
 * Redis carries the counting; these columns exist so the admin screen and the alerts can
 * read a source's state without it, and are written on transitions rather than on every
 * scan. `health_synced_at` is what throttles those writes — without it a source that has
 * been switched off would cost a database write every minute, forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_spool_sources', function (Blueprint $table) {
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('last_healthy_at')->nullable();
            $table->timestamp('health_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fax_spool_sources', function (Blueprint $table) {
            $table->dropColumn([
                'consecutive_failures',
                'last_error',
                'last_error_at',
                'last_healthy_at',
                'health_synced_at',
            ]);
        });
    }
};
