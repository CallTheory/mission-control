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
        Schema::create('better_emails', function (Blueprint $table) {
            $table->id();
            $table->string('client_number');
            $table->string('title');
            $table->string('description')->nullable();
            $table->json('recipients');
            $table->boolean('report_metadata')->default(true);
            $table->boolean('message_history')->default(true);
            $table->string('theme');
            $table->string('subject');
            $table->string('logo')->nullable();
            $table->string('logo_alt')->nullable();
            $table->string('logo_link')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('better_emails');
    }
};
