<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Capability;
use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->abortUnlessUtilityEnabled(Utility::BoardCheck);

        // Supervisor-level view (roles admin/manager/supervisor or a -SUP agent).
        Gate::authorize(Capability::BoardActivity->value);

        return view('utilities.board-activity');
    }
}
