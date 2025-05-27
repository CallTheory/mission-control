<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('mfax_sender_name')->nullable();
            $table->text('mfax_notes')->nullable();
            $table->string('mfax_subject')->nullable();
            $table->text('mfax_api_key')->nullable();
            $table->string('mfax_cover_page_id')->nullable();
            $table->text('mfax_basic_auth_username')->nullable();
            $table->text('mfax_basic_auth_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('mfax_notes');
            $table->dropColumn('mfax_subject');
            $table->dropColumn('mfax_api_key');
            $table->dropColumn('mfax_sender_name');
            $table->dropColumn('mfax_cover_page_id');
            $table->dropColumn('mfax_basic_auth_username');
            $table->dropColumn('mfax_basic_auth_password');
        });
    }
};
