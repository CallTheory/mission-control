<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a fax that never reached the provider be recorded.
 *
 * A pending_faxes row was only written once the provider returned 200, so a failed
 * *submission* left no trace anywhere queryable — only an email, a log line, and the
 * files moved to fail/. The status pages list the provider's own history, and a fax the
 * provider never received cannot appear in it, so there was nothing to see and nothing to
 * resend.
 *
 * api_fax_id becomes nullable because that is precisely what a submission failure lacks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->string('api_fax_id')->nullable()->change();

            // Why it failed, in the words the provider or the exception used.
            $table->text('failure_reason')->nullable()->after('routing_reason');

            // Which step failed: 'submission' never reached the provider and can only be
            // retried from the spool; 'delivery' did reach them and has an api_fax_id.
            $table->string('failure_stage', 16)->nullable()->after('failure_reason');

            // Set when someone puts the spool files back for another attempt, so a fax
            // stops being offered for retry the moment it has been.
            $table->timestamp('retried_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('pending_faxes', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'failure_stage', 'retried_at']);
            $table->string('api_fax_id')->nullable(false)->change();
        });
    }
};
