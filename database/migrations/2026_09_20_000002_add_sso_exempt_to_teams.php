<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams of third-party users who will never have an IdP account. Exempting
     * a team lifts the linked-SSO lock for its members; the SSO-or-2FA policy
     * still applies to them, so they land on 2FA instead.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('sso_exempt')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('sso_exempt');
        });
    }
};
