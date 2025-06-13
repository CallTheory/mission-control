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
            $table->text('is_agent_username')->nullable()->change();
            $table->text('is_agent_password')->nullable()->change();
            $table->text('is_db_user')->nullable()->change();
            $table->text('is_db_pass')->nullable()->change();
            $table->text('stripe_test_secret_key')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            //
        });
    }
};
