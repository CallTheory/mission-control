<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataSourcesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::SystemDataSources->value);

        return view('system.data-sources');
    }
}
