<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use App\Models\InboundEmailRules;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorizeUtility(Utility::InboundEmail);

        $emails = InboundEmail::orderBy('id', 'desc')->get();
        $rules = InboundEmailRules::all();

        return view('utilities.inbound-email')->with('emails', $emails)->with('rules', $rules);
    }
}
