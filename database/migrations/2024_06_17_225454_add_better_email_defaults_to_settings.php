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
            $table->string('better_emails_title')->default('Title within email');
            $table->string('better_emails_description')->default('Descriptive message below title within email (and email preview)');
            $table->string('better_emails_subject')->default('Subject line of email');
            $table->string('better_emails_logo')->default(url('/images/mission-control.png'));
            $table->string('better_emails_logo_alt')->default('Alt text for logo within email');
            $table->string('better_emails_logo_link')->default('https://calltheory.com');
            $table->string('better_emails_button_text')->default('Button text within email');
            $table->string('better_emails_button_link')->default('mailto:support@calltheory.com');
            $table->string('better_emails_theme')->default('standard');
            $table->boolean('better_emails_message_history')->default(true);
            $table->boolean('better_emails_report_metadata')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('better_emails_title');
            $table->dropColumn('better_emails_description');
            $table->dropColumn('better_emails_subject');
            $table->dropColumn('better_emails_logo');
            $table->dropColumn('better_emails_logo_alt');
            $table->dropColumn('better_emails_logo_link');
            $table->dropColumn('better_emails_button_text');
            $table->dropColumn('better_emails_button_link');
            $table->dropColumn('better_emails_theme');
            $table->dropColumn('better_emails_message_history');
            $table->dropColumn('better_emails_report_metadata');
        });
    }
};
