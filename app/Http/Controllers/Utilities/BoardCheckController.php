<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BoardCheckController extends Controller
{
    public function __invoke(Request $request)
    {
        // Access (supervisor OR dispatcher, including -SUP/-DISP agent suffixes)
        // is encoded in the utility.board_check capability and its suffix rules.
        $this->authorizeUtility(Utility::BoardCheck);

        return view('utilities.board-check');
    }
}
