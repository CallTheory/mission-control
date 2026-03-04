<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voicemail_digest_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voicemail_digest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->json('recipients');
            $table->string('subject');
            $table->unsignedInteger('recording_count')->default(0);
            $table->enum('status', ['queued', 'sent', 'failed', 'no_recordings'])->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['voicemail_digest_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voicemail_digest_logs');
    }
};
