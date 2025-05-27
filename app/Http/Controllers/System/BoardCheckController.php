<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Exception;
use Illuminate\View\View;

class BoardCheckController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }
        if (Helpers::isSystemFeatureEnabled('board-check')) {
            return view('system.board-check');
        }

        abort(404);
    }
}
