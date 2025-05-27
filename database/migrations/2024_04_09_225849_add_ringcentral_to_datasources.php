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
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('ringcentral_client_id')->nullable();
            $table->string('ringcentral_client_secret')->nullable();
            $table->text('ringcentral_jwt_token')->nullable();
            $table->text('ringcentral_api_endpoint')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('ringcentral_client_id');
            $table->dropColumn('ringcentral_client_secret');
            $table->dropColumn('ringcentral_jwt_token');
            $table->dropColumn('ringcentral_api_endpoint');
        });
    }
};
