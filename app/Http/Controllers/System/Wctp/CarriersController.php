<?php

declare(strict_types=1);

namespace App\Http\Controllers\System\Wctp;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Support\WctpSectionAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarriersController extends Controller
{
    public function __invoke(Request $request): View
    {
        WctpSectionAccess::authorize(Capability::WctpManage);

        return view('system.wctp.carriers');
    }
}
