<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Jetstream\Jetstream;

class TermsOfServiceController extends Controller
{
    /**
     * Show the terms of service for the application.
     *
     * @param Request $request
     * @return View
     */
    public function show(Request $request): View
    {
        $termsFile = Jetstream::localizedMarkdownPath('terms.md');

        return view('terms', [
            'terms' => Str::markdown(file_get_contents($termsFile)),
        ]);
    }
}
