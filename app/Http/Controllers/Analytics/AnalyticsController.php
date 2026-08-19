<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::AnalyticsView->value);

        return view('analytics');
    }
}
