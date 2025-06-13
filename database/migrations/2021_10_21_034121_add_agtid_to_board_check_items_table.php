<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgtidToBoardCheckItemsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('board_check_items', function (Blueprint $table) {
            $table->bigInteger('agtId')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_check_items', function (Blueprint $table) {
            $table->dropColumn('agtId');
        });
    }
}
