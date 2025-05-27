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
            $table->string('client_db_host')->nullable();
            $table->string('client_db_port')->nullable();
            $table->string('client_db_data')->nullable();
            $table->string('client_db_user')->nullable();
            $table->text('client_db_pass')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('client_db_host');
            $table->dropColumn('client_db_port');
            $table->dropColumn('client_db_data');
            $table->dropColumn('client_db_user');
            $table->dropColumn('client_db_pass');
        });
    }
};
