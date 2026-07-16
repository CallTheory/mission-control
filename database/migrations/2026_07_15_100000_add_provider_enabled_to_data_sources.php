<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add explicit on/off toggles for each cloud fax provider. Previously a provider was
     * shown purely because its API keys were present, which meant the only way to hide it
     * was to delete the credentials. These flags decouple "is it visible" from "does it
     * have keys" so a provider can be turned off without wiping its configuration.
     */
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->boolean('ringcentral_enabled')->default(false)->after('ringcentral_api_endpoint');
            $table->boolean('mfax_enabled')->default(false)->after('mfax_api_key');
        });

        // Onboarding: any provider that already has credentials stays visible after deploy.
        DB::table('data_sources')->whereNotNull('ringcentral_client_id')->update(['ringcentral_enabled' => true]);
        DB::table('data_sources')->whereNotNull('mfax_api_key')->update(['mfax_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['ringcentral_enabled', 'mfax_enabled']);
        });
    }
};
