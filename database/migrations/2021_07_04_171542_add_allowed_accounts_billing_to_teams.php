<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAllowedAccountsBillingToTeams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->text('allowed_accounts')->nullable();
            $table->text('allowed_billing')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('allowed_accounts');
            $table->dropColumn('allowed_billing');
        });
    }
}
