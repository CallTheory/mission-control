<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System feature flags, previously one encrypted file per flag on the
     * default disk. A row per flag rather than a column per flag, so adding a
     * utility needs no schema change -- and an unknown key simply reads as
     * disabled.
     */
    public function up(): void
    {
        Schema::create('system_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_features');
    }
};
