<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInboundEmailRulesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inbound_email_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('rules');
            $table->boolean('enabled')->default(0);
            $table->foreignId('mergecomm_trigger_id')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_email_rules');
    }
}
