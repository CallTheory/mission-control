<?php

declare(strict_types=1);

namespace App\Http\Controllers\System\Wctp;

use App\Http\Controllers\Controller;
use App\Support\WctpSectionAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The WCTP gateway section index: what the screen's tabs used to be, as links.
 */
class IndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Either capability is enough to see the index; each link on it is then
        // rendered only if the user can actually open that page.
        WctpSectionAccess::authorizeAny();

        return view('system.wctp.index');
    }
}
