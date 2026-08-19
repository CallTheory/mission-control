<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigEditorController extends Controller
{
    public function __invoke(Request $request)
    {
        // The config editor issues raw SELECT/UPDATE against the production
        // Amtelco SQL Server; the utility.config_editor capability is granted to
        // team admins only (see config/roles.php).
        $this->authorizeUtility(Utility::ConfigEditor);

        return view('utilities.config-editor');
    }
}
