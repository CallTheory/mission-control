<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which carrier carried each message, and that carrier's own id for it.
 *
 * `twilio_sid` predates multi-carrier support and is kept: it is what the message
 * viewer searches and displays, and Twilio messages still write it. New code reads
 * `provider_message_id`, which holds the Bandwidth message id or the Commio guid
 * for messages that went out those doors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('twilio_sid')->index();
            $table->string('provider_message_id')->nullable()->after('provider')->index();

            // An inbound MMS can legitimately carry media and no text at all, which
            // the NOT NULL column turned into a 500 -- and a 5xx makes the carrier
            // redeliver the same message forever.
            $table->text('message')->nullable()->change();
        });

        // Everything already in the table went out through Twilio, by definition:
        // it was the only carrier.
        DB::table('wctp_messages')->update(['provider' => 'twilio']);

        DB::table('wctp_messages')
            ->whereNotNull('twilio_sid')
            ->update(['provider_message_id' => DB::raw('twilio_sid')]);
    }

    public function down(): void
    {
        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_message_id']);
        });

        // Rows with no text would fail the NOT NULL constraint on the way back.
        DB::table('wctp_messages')->whereNull('message')->update(['message' => '']);

        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->text('message')->nullable(false)->change();
        });
    }
};
