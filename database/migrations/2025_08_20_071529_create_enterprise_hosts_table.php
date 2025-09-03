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
        Schema::create('enterprise_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('senderID')->unique()->index();
            $table->text('securityCode'); // Will be encrypted
            $table->boolean('enabled')->default(true)->index();
            $table->string('callback_url')->nullable(); // URL to forward inbound SMS
            $table->json('phone_numbers')->nullable(); // Phone numbers mapped to this host
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->index(['enabled', 'senderID']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enterprise_hosts');
    }
};