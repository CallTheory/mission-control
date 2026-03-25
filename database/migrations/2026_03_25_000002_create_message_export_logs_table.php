<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_export_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('export_name');
            $table->string('client_number');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->unsignedInteger('message_count')->default(0);
            $table->string('status')->default('queued'); // queued, completed, failed, no_messages
            $table->text('error_message')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_export_logs');
    }
};
