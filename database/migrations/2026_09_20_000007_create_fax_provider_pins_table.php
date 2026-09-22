<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides that force particular faxes through a particular provider.
 *
 * The operational need is narrow and real: when one provider's route to a given
 * destination starts failing, you want to move just that destination — or just the
 * affected client — without changing anything for everyone else.
 *
 * Matches the shape EnterpriseHost.number_providers already uses for WCTP carriers, with
 * numbers normalised through App\Support\PhoneNumber so `+1 (555) 123-4567` and
 * `5551234567` are the same pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fax_provider_pins', function (Blueprint $table) {
            $table->id();

            // 'number' = the recipient's fax number; 'account' = the Intelligent Series
            // client the fax belongs to.
            $table->string('match_type', 16);
            $table->string('match_value', 64);
            $table->string('provider', 32);

            // Per pin, because the reason for pinning decides the answer. A pin that
            // exists *because* the other provider is broken for this number must not fall
            // back to it; one that merely expresses a preference may.
            $table->boolean('allow_failover')->default(false);

            $table->boolean('enabled')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['match_type', 'match_value']);
            $table->index(['enabled', 'match_type', 'match_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fax_provider_pins');
    }
};
