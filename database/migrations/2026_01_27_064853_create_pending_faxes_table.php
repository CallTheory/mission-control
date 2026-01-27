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
        Schema::create('pending_faxes', function (Blueprint $table) {
            $table->id();
            $table->string('api_fax_id');
            $table->string('fax_provider'); // mfax or ringcentral
            $table->unsignedBigInteger('job_id');
            $table->string('fs_file_name');
            $table->string('cap_file');
            $table->string('filename');
            $table->string('phone');
            $table->string('original_status');
            $table->string('delivery_status')->default('pending'); // pending, success, failed
            $table->unsignedInteger('poll_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_status', 'fax_provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_faxes');
    }
};
