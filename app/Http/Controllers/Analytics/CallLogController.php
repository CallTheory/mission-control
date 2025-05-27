<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CallLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('analytics.call-log');
    }
}
