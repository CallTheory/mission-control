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
        Schema::create('recording_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('client_number')->nullable();
            $table->string('billing_code')->nullable();
            $table->json('recipients');
            $table->string('subject');
            $table->string('schedule_type'); // hourly, daily, weekly, monthly
            $table->string('schedule_time')->nullable(); // HH:MM format
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable(); // 0-6 for weekly
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable(); // 1-31 for monthly
            $table->boolean('include_transcription')->default(true);
            $table->boolean('include_call_metadata')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recording_emails');
    }
};
