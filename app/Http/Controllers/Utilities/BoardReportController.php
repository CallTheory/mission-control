<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Capability;
use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->abortUnlessUtilityEnabled(Utility::BoardCheck);

        // Supervisor-level view (roles admin/manager/supervisor or a -SUP agent).
        Gate::authorize(Capability::BoardReport->value);

        return view('utilities.board-report');
    }
}
