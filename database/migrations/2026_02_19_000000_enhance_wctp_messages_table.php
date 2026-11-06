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

        // Expand the status enum to include 'queued'.
        $this->setStatuses(['pending', 'queued', 'sent', 'delivered', 'failed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any 'queued' rows back to 'pending' before shrinking the enum.
        DB::table('wctp_messages')->where('status', 'queued')->update(['status' => 'pending']);
        $this->setStatuses(['pending', 'sent', 'delivered', 'failed']);

        Schema::table('wctp_messages', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'submitted_at', 'processed_at']);
        });
    }

    /**
     * Replace the set of values the status column accepts.
     *
     * Laravel's enum() is a native ENUM on MySQL/MariaDB, but on PostgreSQL it is a
     * varchar with a CHECK constraint, which Postgres names {table}_{column}_check.
     * MODIFY COLUMN is MySQL-only syntax and was previously sent to Postgres too,
     * which failed the deploy. SQLite's CHECK lives inside the table definition and
     * cannot be altered in place; it was never widened there, as before.
     *
     * @param  list<string>  $statuses
     */
    private function setStatuses(array $statuses): void
    {
        $list = implode(', ', array_map(fn (string $status): string => "'{$status}'", $statuses));

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE wctp_messages MODIFY COLUMN status ENUM({$list}) DEFAULT 'pending'"),
            'pgsql' => DB::unprepared(
                'ALTER TABLE wctp_messages DROP CONSTRAINT IF EXISTS wctp_messages_status_check; '
                ."ALTER TABLE wctp_messages ADD CONSTRAINT wctp_messages_status_check CHECK (status IN ({$list}))"
            ),
            default => null,
        };
    }
};
