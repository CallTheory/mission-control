<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Jetstream\Jetstream;

class PrivacyPolicyController extends Controller
{
    /**
     * Show the privacy policy for the application.
     *
     * @param Request $request
     * @return View
     */
    public function show(Request $request): View
    {
        $policyFile = Jetstream::localizedMarkdownPath('policy.md');

        return view('policy', [
            'policy' => Str::markdown(file_get_contents($policyFile)),
        ]);
    }
}
