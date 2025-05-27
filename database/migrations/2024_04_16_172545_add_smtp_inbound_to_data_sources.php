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
            $table->string('amtelco_inbound_smtp_host')->nullable();
            $table->integer('amtelco_inbound_smtp_port')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('amtelco_inbound_smtp_host');
            $table->dropColumn('amtelco_inbound_smtp_port');
        });
    }
};
