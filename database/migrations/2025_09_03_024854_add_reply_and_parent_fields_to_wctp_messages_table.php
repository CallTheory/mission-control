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
        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->string('reply_with')->nullable()->after('message');
            $table->unsignedBigInteger('parent_message_id')->nullable()->after('twilio_sid');
            $table->foreign('parent_message_id')->references('id')->on('wctp_messages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->dropForeign(['parent_message_id']);
            $table->dropColumn(['reply_with', 'parent_message_id']);
        });
    }
};