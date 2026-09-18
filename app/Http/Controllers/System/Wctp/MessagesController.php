<?php

declare(strict_types=1);

namespace App\Http\Controllers\System\Wctp;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Support\WctpSectionAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The message log. Gated separately from the setup screens so read access to
 * traffic can be granted without granting configuration.
 */
class MessagesController extends Controller
{
    public function __invoke(Request $request): View
    {
        WctpSectionAccess::authorize(Capability::WctpMessages);

        return view('system.wctp.messages');
    }
}
