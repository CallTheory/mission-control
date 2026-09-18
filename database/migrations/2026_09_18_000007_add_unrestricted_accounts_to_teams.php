<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "this team may see every account" a decision rather than an absence.
 *
 * Empty allow-lists have always meant "no restriction" -- twelve stats models emit no
 * WHERE predicate for them, and every per-record check returns true. That is a workable
 * convention, but a null column cannot distinguish a team someone deliberately left open
 * from one nobody has configured yet. This column records the difference.
 *
 * Backfill therefore states what is already true of existing teams: a team with no lists
 * is unrestricted today, so it is marked unrestricted. Nothing changes for them. New
 * teams default to false and must be configured -- lists, or the box ticked -- before
 * they reach account-scoped call data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('unrestricted_accounts')->default(false)->after('allowed_billing');
        });

        // Backfilled in PHP rather than SQL so the emptiness test is character-for-
        // character the one the runtime applies. SQL TRIM() strips spaces only -- not
        // newlines or tabs -- across MySQL, Postgres and SQLite alike, which would leave
        // a whitespace-only list looking configured here while the application reads it
        // as empty, and quietly revoke access that team has today. The teams table is
        // small enough that iterating costs nothing.
        DB::table('teams')
            ->select(['id', 'allowed_accounts', 'allowed_billing'])
            ->orderBy('id')
            ->chunk(200, function ($teams) {
                $unrestricted = $teams
                    ->filter(fn ($team) => trim((string) $team->allowed_accounts) === ''
                        && trim((string) $team->allowed_billing) === '')
                    ->pluck('id')
                    ->all();

                if ($unrestricted !== []) {
                    DB::table('teams')
                        ->whereIn('id', $unrestricted)
                        ->update(['unrestricted_accounts' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('unrestricted_accounts');
        });
    }
};
