<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credentials for the Microsoft Entra ID app registration the token watcher
     * authenticates as. Read-only in Azure: the registration only ever holds the
     * Graph application permission Application.Read.All.
     *
     * These sit on `data_sources` with every other third-party credential, and are
     * edited from the Entra ID tile on System -> Integrations.
     */
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('azure_tenant_id', 64)->nullable()
                ->comment('Entra tenant (directory) ID');
            $table->string('azure_client_id', 64)->nullable()
                ->comment('Application (client) ID of the watcher app registration');

            // text, not string: the serializing encrypter inflates a ~40-char
            // secret well past 255 bytes.
            $table->text('azure_client_secret')->nullable()
                ->comment('Encrypted at rest via the EncryptedSerialized cast');

            $table->boolean('azure_enabled')->default(false)
                ->comment('Whether the daily credential sweep runs');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn([
                'azure_tenant_id',
                'azure_client_id',
                'azure_client_secret',
                'azure_enabled',
            ]);
        });
    }
};
