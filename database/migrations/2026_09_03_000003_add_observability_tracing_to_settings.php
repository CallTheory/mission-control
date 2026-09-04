<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in OpenTelemetry tracing, exported over OTLP to Grafana Tempo
     * (normally via a local Alloy agent). Off and unconfigured on a fresh
     * install; an administrator configures it at System -> Observability.
     *
     * Value columns are nullable so that null means "not configured here, use
     * the config default". The enable toggle is a plain non-nullable boolean,
     * matching the existing `mcp_enabled` convention, because an env var — not
     * a null column — is what overrides it.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('observability_tracing_enabled')->default(false);

            $table->string('observability_tracing_endpoint')->nullable()
                ->comment('OTLP base URL; /v1/traces is appended automatically');
            $table->string('observability_tracing_protocol', 20)->nullable()
                ->comment('http/protobuf or http/json');

            $table->string('observability_tracing_auth_username')->nullable()
                ->comment('Grafana Cloud: the instance ID');
            $table->text('observability_tracing_auth_token')->nullable()
                ->comment('Encrypted at rest via the EncryptedSerialized cast');

            $table->string('observability_tracing_service_name', 100)->nullable();

            $table->decimal('observability_tracing_sample_rate', 5, 4)->nullable();

            $table->boolean('observability_tracing_db_spans_enabled')->default(false);
            $table->unsignedInteger('observability_tracing_db_slow_query_ms')->nullable()
                ->comment('0 or null records every query; e.g. 50 records only slow ones');

            $table->unsignedSmallInteger('observability_tracing_export_timeout_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'observability_tracing_enabled',
                'observability_tracing_endpoint',
                'observability_tracing_protocol',
                'observability_tracing_auth_username',
                'observability_tracing_auth_token',
                'observability_tracing_service_name',
                'observability_tracing_sample_rate',
                'observability_tracing_db_spans_enabled',
                'observability_tracing_db_slow_query_ms',
                'observability_tracing_export_timeout_ms',
            ]);
        });
    }
};
