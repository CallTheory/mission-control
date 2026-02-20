<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('failed_at');
            $table->timestamp('processed_at')->nullable()->after('submitted_at');
        });

        // Expand the status enum to include 'queued'
        // For MySQL/MariaDB, we need to modify the enum directly
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE wctp_messages MODIFY COLUMN status ENUM('pending', 'queued', 'sent', 'delivered', 'failed') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // First update any 'queued' rows back to 'pending' before shrinking enum
            DB::table('wctp_messages')->where('status', 'queued')->update(['status' => 'pending']);
            DB::statement("ALTER TABLE wctp_messages MODIFY COLUMN status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending'");
        }

        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'submitted_at', 'processed_at']);
        });
    }
};
