<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBoardCheckItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('board_check_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('msgId');
            $table->bigInteger('callId');
            $table->text('comments')->nullable();

            //dispatcher type level to mark it okay
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            //dispatcher type level to mark it problematic
            $table->string('problem_found_by')->nullable();
            $table->timestamp('problem_found_at')->nullable();

            //supervisor level verifies the problem
            $table->string('problem_verified_by')->nullable();
            $table->timestamp('problem_verified_at')->nullable();

            //supervisor level mark item as not problematic
            $table->string('marked_ok_by')->nullable();
            $table->timestamp('marked_ok_at')->nullable();

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
        Schema::dropIfExists('board_check_items');
    }
}
