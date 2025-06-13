<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInboundIdToMergcommIswebTriggers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mergecomm_web_triggers', function (Blueprint $table) {
            $table->bigInteger('inboundID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mergecomm_web_triggers', function (Blueprint $table) {
            $table->dropColumn('inboundID');
        });
    }
}
