<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\System\Settings;
use Illuminate\Database\Eloquent\Model;

/**
 * The inline System settings panels that write the single `Settings` row -- switch
 * timezone, board check configuration, the MCP server, SAML.
 *
 * Identical in shape to {@see EditsDataSourceSettings}; only the row differs. The
 * Settings row is created on first use, because several of these screens are reachable
 * on a fresh install before anything has written it.
 */
trait EditsSystemSettings
{
    use EditsDataSourceSettings;

    protected function settingsRecord(): Model
    {
        return Settings::first() ?? new Settings;
    }
}
