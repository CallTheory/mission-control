<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('is_web_api_endpoint')->nullable();
            $table->string('is_db_host')->nullable();
            $table->string('is_db_port')->nullable();
            $table->string('is_db_data')->nullable();
            $table->string('is_db_user')->nullable();
            $table->string('is_db_pass')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
}
