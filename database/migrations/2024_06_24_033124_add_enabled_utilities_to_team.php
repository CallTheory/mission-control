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
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('utility_api_gateway')->default(false);
            $table->boolean('utility_better_emails')->default(false);
            $table->boolean('utility_board_check')->default(false);
            $table->boolean('utility_call_lookup')->default(false);
            $table->boolean('utility_card_processing')->default(false);
            $table->boolean('utility_cloud_faxing')->default(false);
            $table->boolean('utility_database_health')->default(false);
            $table->boolean('utility_directory_search')->default(false);
            $table->boolean('utility_inbound_email')->default(false);
            $table->boolean('utility_music_streams')->default(false);
            $table->boolean('utility_script_search')->default(false);
            $table->boolean('utility_wctp_gateway')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('utility_api_gateway');
            $table->dropColumn('utility_better_emails');
            $table->dropColumn('utility_board_check');
            $table->dropColumn('utility_call_lookup');
            $table->dropColumn('utility_card_processing');
            $table->dropColumn('utility_cloud_faxing');
            $table->dropColumn('utility_database_health');
            $table->dropColumn('utility_directory_search');
            $table->dropColumn('utility_inbound_email');
            $table->dropColumn('utility_music_streams');
            $table->dropColumn('utility_script_search');
            $table->dropColumn('utility_wctp_gateway');
        });
    }
};
