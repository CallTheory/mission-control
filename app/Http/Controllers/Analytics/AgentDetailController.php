<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentDetailController extends Controller
{
    public function __invoke(Request $request, $agent_name): View
    {
        return view('analytics.agent-details')->with('agent_name', $agent_name);
    }
}
