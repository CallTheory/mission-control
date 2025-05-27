<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class RedirectHomeController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to('/dashboard');
    }
}
