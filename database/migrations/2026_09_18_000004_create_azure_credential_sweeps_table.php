<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per sweep attempt, successful or not.
     *
     * This is what makes a dead collector visible: the dashboard reads the most
     * recent row to decide whether the credential table it is showing is current,
     * and a failed sweep keeps its error message here instead of only in the log.
     */
    public function up(): void
    {
        Schema::create('azure_credential_sweeps', function (Blueprint $table) {
            $table->id();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16)->comment('running, success or failed');

            $table->unsignedInteger('applications')->default(0);
            $table->unsignedInteger('service_principals')->default(0);
            $table->unsignedInteger('credentials_seen')->default(0);
            $table->unsignedInteger('credentials_added')->default(0);
            $table->unsignedInteger('credentials_removed')->default(0);
            $table->unsignedInteger('alerts_sent')->default(0);

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('azure_credential_sweeps');
    }
};
