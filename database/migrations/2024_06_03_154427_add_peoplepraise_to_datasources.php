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
            $table->text('people_praise_basic_auth_user')->nullable();
            $table->text('people_praise_basic_auth_pass')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('people_praise_basic_auth_user');
            $table->dropColumn('people_praise_basic_auth_pass');
        });
    }
};
