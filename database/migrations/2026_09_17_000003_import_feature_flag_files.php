<?php

use App\Enums\Utility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Carry an existing install's flags across from the encrypted files they
     * used to live in, so the features a system already has switched on stay
     * switched on across this deploy.
     *
     * A flag file counted as enabled only if its contents decrypted to the
     * flag's own name -- that was the tamper check -- so that is the test
     * applied here too. Files are left where they are rather than deleted:
     * nothing reads them any more, and leaving them means a rollback of this
     * migration puts the old behaviour back with its data intact.
     */
    private const EXTRA_FLAGS = ['transcription', 'screencaptures'];

    public function up(): void
    {
        $now = now();

        foreach ($this->flagNames() as $flag) {
            if (! $this->fileEnabled($flag)) {
                continue;
            }

            DB::table('system_features')->updateOrInsert(
                ['key' => $flag],
                ['enabled' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    /**
     * Irreversible on purpose. The flag files are still on disk, so rolling
     * back restores the previous source of truth; wiping rows here would
     * instead destroy any flag an operator has changed since the deploy.
     */
    public function down(): void
    {
        //
    }

    /**
     * @return array<int, string>
     */
    private function flagNames(): array
    {
        $utilities = array_map(
            fn (Utility $utility): string => $utility->systemFlag(),
            Utility::cases()
        );

        return array_values(array_unique([...$utilities, ...self::EXTRA_FLAGS]));
    }

    private function fileEnabled(string $flag): bool
    {
        $path = "feature-flags/{$flag}.flag";

        try {
            if (! Storage::fileExists($path)) {
                return false;
            }

            return decrypt(Storage::get($path)) === $flag;
        } catch (Throwable) {
            // Unreadable or encrypted with a different key: treat as off, the
            // same as the old reader did.
            return false;
        }
    }
};
