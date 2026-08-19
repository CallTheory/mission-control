<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Client-specific grants keyed on the linked Intelligent agent's Name
        // (e.g. a '-SUP' suffix). team_id NULL applies to every team; a set
        // team_id scopes the rule to one team.
        Schema::create('suffix_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('match_type')->default('contains'); // contains|suffix|prefix
            $table->string('pattern');
            $table->string('capability');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suffix_rules');
    }
};
