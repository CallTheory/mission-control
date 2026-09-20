<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both default to false so an existing install behaves exactly as it did
     * until an admin opts in. Turning either on can lock people out, so it is
     * never something an upgrade should do on its own.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // An account with saml_linked_id may only sign in through the IdP.
            $table->boolean('auth_enforce_linked_sso')->default(false);

            // Every user must have SSO linked or 2FA enabled.
            $table->boolean('auth_require_sso_or_2fa')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['auth_enforce_linked_sso', 'auth_require_sso_or_2fa']);
        });
    }
};
