<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Utilities\RenderMessageSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Exception;

class SystemController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {

        $summary = 'Test message';
        $string = RenderMessageSummary::htmlToImage($summary, ['timeout' => 300]);
        dd($string);

        if ($request->user()->currentTeam->personal_team === false) {
            if (
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin')
            ) {
                return view('system');
            }
        }

        abort(403);
    }
}
