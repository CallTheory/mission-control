<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('client_number');
            $table->string('client_name')->nullable();
            $table->text('selected_fields'); // encrypted JSON array of field names
            $table->text('filter_field')->nullable(); // encrypted
            $table->text('filter_value')->nullable(); // encrypted
            $table->boolean('include_call_info')->default(true);
            $table->json('recipients')->nullable();
            $table->string('subject')->default('Message Export');
            $table->string('schedule_type'); // manual, immediate, hourly, daily, weekly, monthly
            $table->string('schedule_time')->nullable(); // HH:MM format
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable(); // 0-6 for weekly
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable(); // 1-31 for monthly
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'enabled']);
            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_exports');
    }
};
