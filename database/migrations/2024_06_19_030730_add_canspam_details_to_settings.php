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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('better_emails_canspam_address')->nullable();
            $table->string('better_emails_canspam_address2')->nullable();
            $table->string('better_emails_canspam_city')->nullable();
            $table->string('better_emails_canspam_state')->nullable();
            $table->string('better_emails_canspam_postal')->nullable();
            $table->string('better_emails_canspam_country')->default('US');
            $table->string('better_emails_canspam_email')->nullable();
            $table->string('better_emails_canspam_phone')->nullable();
            $table->string('better_emails_canspam_company')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('better_emails_canspam_address');
            $table->dropColumn('better_emails_canspam_address2');
            $table->dropColumn('better_emails_canspam_city');
            $table->dropColumn('better_emails_canspam_state');
            $table->dropColumn('better_emails_canspam_postal');
            $table->dropColumn('better_emails_canspam_country');
            $table->dropColumn('better_emails_canspam_email');
            $table->dropColumn('better_emails_canspam_phone');
            $table->dropColumn('better_emails_canspam_company');
        });
    }
};
