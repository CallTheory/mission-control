<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in GlitchTip/Sentry exception reporting. Everything is off and empty
     * on a fresh install; an administrator configures it in
     * System -> Observability.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('observability_errors_enabled')->default(false);

            // text, not string: the serializing encrypter inflates a ~60-char
            // DSN well past 255 bytes.
            $table->text('observability_errors_dsn')->nullable()
                ->comment('Encrypted at rest via the EncryptedSerialized cast');

            $table->string('observability_environment')->nullable()
                ->comment('Reported environment name; falls back to app.env');
            $table->string('observability_release')->nullable();

            $table->decimal('observability_errors_sample_rate', 4, 3)->default(1.000);

            $table->timestamp('observability_last_test_at')->nullable();
            $table->string('observability_last_test_status')->nullable()
                ->comment('ok, or a short error string from the test button');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'observability_errors_enabled',
                'observability_errors_dsn',
                'observability_environment',
                'observability_release',
                'observability_errors_sample_rate',
                'observability_last_test_at',
                'observability_last_test_status',
            ]);
        });
    }
};
