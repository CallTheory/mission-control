<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\Role;
use App\Models\SuffixRule;
use App\Models\Team;

/**
 * Seeds a team with the default system roles (and their capabilities) from the
 * config/roles.php template. Idempotent — safe to run repeatedly; it only fills
 * in what is missing and never overwrites capabilities an admin has since
 * edited on an existing system role.
 */
class SeedDefaultRolesForTeam
{
    public function __invoke(Team $team): void
    {
        foreach (config('roles.defaults', []) as $key => $definition) {
            $role = Role::firstOrNew([
                'team_id' => $team->id,
                'key' => $key,
            ]);

            $isNew = ! $role->exists;

            $role->fill([
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null,
                'is_system' => true,
                'sort_order' => $definition['sort_order'] ?? 0,
            ])->save();

            // Only seed capabilities for freshly created roles so we don't clobber
            // an admin's later edits when this runs again.
            if ($isNew) {
                $role->syncCapabilities($definition['capabilities'] ?? []);
            }
        }
    }

    /**
     * Seed the global default suffix rules (team_id NULL) if none exist yet.
     */
    public function seedGlobalSuffixRules(): void
    {
        if (SuffixRule::whereNull('team_id')->exists()) {
            return;
        }

        foreach (config('roles.suffix_rules', []) as $rule) {
            foreach ($rule['capabilities'] as $capability) {
                SuffixRule::create([
                    'team_id' => null,
                    'match_type' => $rule['match_type'],
                    'pattern' => $rule['pattern'],
                    'capability' => $capability,
                ]);
            }
        }
    }
}
