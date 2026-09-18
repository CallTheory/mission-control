<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which Intelligent Series account each fax belongs to, and when it was last
     * polled for a delivery status.
     *
     * mFax carried the account number as a provider-side tag; RingCentral has no such
     * concept, so the account is stored here instead. That makes it available to both
     * providers' dashboards and to the spool file listings, where an unidentified
     * phantom .cap/.fs previously gave no clue whose fax it was.
     */
    public function up(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->string('client_number')->nullable()->after('phone');
            $table->string('client_name')->nullable()->after('client_number');

            // Lets the status poller space its checks out per record instead of hitting
            // the provider for every pending fax on every single run.
            $table->timestamp('last_polled_at')->nullable()->after('poll_attempts');

            $table->index('client_number');
            $table->index(['delivery_status', 'last_polled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->dropIndex(['delivery_status', 'last_polled_at']);
            $table->dropIndex(['client_number']);
            $table->dropColumn(['client_number', 'client_name', 'last_polled_at']);
        });
    }
};
