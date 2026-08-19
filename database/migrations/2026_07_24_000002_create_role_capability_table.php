<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_capability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            // Stores the App\Enums\Capability string value. Capabilities are
            // code-defined, so there is intentionally no capabilities table.
            $table->string('capability');
            $table->timestamps();

            $table->unique(['role_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_capability');
    }
};
