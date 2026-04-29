<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_exports')
            ->where('schedule_type', 'immediate')
            ->update([
                'schedule_type' => 'manual',
                'next_run_at' => null,
                'schedule_time' => null,
                'schedule_day_of_week' => null,
                'schedule_day_of_month' => null,
            ]);

        DB::table('message_exports')
            ->where('enabled', true)
            ->where('schedule_type', '!=', 'manual')
            ->whereNull('next_run_at')
            ->update(['next_run_at' => now()]);
    }

    public function down(): void
    {
        // No-op: data normalization is one-way.
    }
};
