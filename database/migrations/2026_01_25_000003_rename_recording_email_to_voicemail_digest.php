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
        Schema::rename('recording_emails', 'voicemail_digests');

        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('utility_recording_email', 'utility_voicemail_digest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('voicemail_digests', 'recording_emails');

        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('utility_voicemail_digest', 'utility_recording_email');
        });
    }
};
