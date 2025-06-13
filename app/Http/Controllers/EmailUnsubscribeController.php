<?php

namespace App\Http\Controllers;

use App\Models\BetterEmails;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function __construct()
    {
        // $this->middleware('signed');
    }

    public function __invoke(Request $request): View
    {
        $email = $request->email;
        $betterEmail = BetterEmails::findOrFail($request->eid);

        return view('emails.better-emails.email-unsubscribe')->with([
            'email' => $request->email,
            'campaign' => $betterEmail,
        ]);
    }
}
