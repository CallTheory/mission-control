<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientDetailController extends Controller
{
    public function __invoke(Request $request, $client_number): View
    {
        return view('analytics.client-details')->with('client_number', $client_number);
    }
}
