<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alerting for the Azure token watcher. Off with no recipients on a fresh
     * install; configured in System -> Azure Tokens.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('azure_tokens_alert_enabled')->default(false);
            $table->text('azure_tokens_alert_recipients')->nullable()
                ->comment('Comma or newline separated addresses for expiry alerts');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'azure_tokens_alert_enabled',
                'azure_tokens_alert_recipients',
            ]);
        });
    }
};
