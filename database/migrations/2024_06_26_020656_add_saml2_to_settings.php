<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('saml2_enabled')->default(false);
            $table->boolean('saml2_stateless_redirect')->default(false);
            $table->boolean('saml2_stateless_callback')->default(false);
            $table->string('saml2_metadata_url')->nullable();
            $table->text('saml2_metadata_xml')->nullable();
            $table->text('saml2_sp_certificate')->nullable();
            $table->text('saml2_sp_private_key')->nullable();
            $table->boolean('saml2_sp_sign_assertions')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('saml2_enabled');
            $table->dropColumn('saml2_stateless_redirect');
            $table->dropColumn('saml2_stateless_callback');
            $table->dropColumn('saml2_metadata_url');
            $table->dropColumn('saml2_metadata_xml');
            $table->dropColumn('saml2_sp_certificate');
            $table->dropColumn('saml2_sp_private_key');
            $table->dropColumn('saml2_sp_sign_assertions');
        });
    }
};
