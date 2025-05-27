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
        Schema::table('data_sources', function (Blueprint $table) {

            $table->string('ai_inference_endpoint')->nullable();
            $table->text('ai_inference_apikey')->nullable();
            $table->string('ai_inference_timeout')->nullable();
            $table->string('ai_inference_model')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('ai_inference_endpoint');
            $table->dropColumn('ai_inference_apikey');
            $table->dropColumn('ai_inference_timeout');
            $table->dropColumn('ai_inference_model');
        });
    }
};
