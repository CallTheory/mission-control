<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('api_whitelist')->nullable(); // JSON array of IP addresses to allow
            $table->boolean('require_api_tokens')->default(false); // require sanctum auth
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('api_whitelist');
            $table->dropColumn('require_api_tokens');
        });
    }
};
