<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which carrier owns each of a host's phone numbers.
 *
 * A DID belongs to exactly one carrier, and a host can hold numbers from several at
 * once, so the carrier is a property of the number rather than of the host. Stored
 * as a map of digits-only number => provider key alongside the existing
 * `phone_numbers` list, which stays the authoritative set of numbers: an entry here
 * for a number the host no longer has is simply ignored, and a number with no entry
 * uses the system default carrier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_hosts', function (Blueprint $table) {
            $table->json('number_providers')->nullable()->after('phone_numbers');
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_hosts', function (Blueprint $table) {
            $table->dropColumn('number_providers');
        });
    }
};
