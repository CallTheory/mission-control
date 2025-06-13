<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBoardCheckSystemSettings extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('board_check_starting_msgId')->nullable();
            $table->string('board_check_peoplepraise_export_file_location')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('board_check_starting_msgId');
            $table->dropColumn('board_check_peoplepraise_export_file_location');
        });
    }
}
