<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMergeCommISWebTriggersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('mergecomm_web_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('clientNumber');
            $table->string('login');
            $table->string('password');
            $table->string('clientId');
            $table->string('message')->nullable();
            $table->string('apiKey');
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
        Schema::dropIfExists('mergecomm_web_triggers');
    }
}
