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
        Schema::create('wctp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_host_id')->constrained()->cascadeOnDelete();
            $table->string('to'); // Phone number (recipient for outbound, sender for inbound)
            $table->string('from'); // Phone number (sender for outbound, recipient for inbound)
            $table->text('message');
            $table->string('wctp_message_id')->index(); // WCTP message ID or Twilio SID for inbound
            $table->string('twilio_sid')->nullable()->index(); // Twilio message SID
            $table->enum('direction', ['inbound', 'outbound'])->index();
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index(['enterprise_host_id', 'direction']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wctp_messages');
    }
};