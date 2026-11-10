<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which RingCentral extension sent each fax.
 *
 * A sent fax lives in the sending extension's message store, so polling its status or
 * resending it has to authenticate as that same extension. Null means the primary
 * extension — which is also what every row written before this column existed was sent
 * through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->string('ringcentral_sender', 64)->nullable()->after('fax_provider');
            $table->string('ringcentral_extension_id', 32)->nullable()->after('ringcentral_sender');
        });
    }

    public function down(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->dropColumn(['ringcentral_sender', 'ringcentral_extension_id']);
        });
    }
};
