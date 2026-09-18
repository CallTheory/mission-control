<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per client secret or certificate found on an Entra app registration
     * or service principal, refreshed by the daily sweep.
     *
     * Graph never returns credential VALUES, only metadata, so nothing in this
     * table is a secret -- it is expiry dates and names.
     *
     * Days-remaining and the red/yellow/green status are computed at read time
     * rather than stored: a stored "days left" is wrong the moment the sweep
     * finishes, and a stored status turns a dead collector into a healthy-looking
     * tenant.
     */
    public function up(): void
    {
        Schema::create('azure_credentials', function (Blueprint $table) {
            $table->id();

            // Graph identifiers are GUIDs; 64 leaves headroom while keeping the
            // composite unique index below comfortably inside MySQL's key limit.
            $table->string('key_id', 64)
                ->comment('Graph keyId -- the credential identifier');

            $table->string('app_object_id', 64)
                ->comment('Graph object id of the application or service principal');
            $table->string('app_client_id', 64)->comment('Graph appId');
            $table->string('app_name');

            $table->string('source', 32)->comment('application or servicePrincipal');
            $table->string('cred_type', 32)->comment('secret or certificate');

            $table->string('cred_name')->nullable()
                ->comment('Credential displayName, frequently blank in Azure');
            $table->string('hint')->nullable()
                ->comment('Secret hint (first characters) or certificate thumbprint');

            $table->timestamp('start_utc')->nullable();
            $table->timestamp('end_utc')->comment('Expiry, UTC -- the number that matters');

            $table->timestamp('last_seen_utc')
                ->comment('Set every sweep; an older value means Azure no longer returns it');
            $table->timestamp('removed_at')->nullable()
                ->comment('Set when a completed sweep no longer saw the credential');

            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('alerted_threshold')->nullable()
                ->comment('Smallest days-remaining threshold already alerted on; keeps alerts idempotent');

            $table->timestamps();

            // keyId is unique per credential but NOT across objects: a SAML signing
            // certificate commonly appears on both an application and its service
            // principal carrying the same keyId, and both rows are legitimate.
            $table->unique(['app_object_id', 'key_id']);

            $table->index('end_utc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('azure_credentials');
    }
};
