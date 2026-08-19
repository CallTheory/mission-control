<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VoicemailDigestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeUtility(Utility::VoicemailDigest);

        return view('utilities.voicemail-digest');
    }

    public function history(Request $request)
    {
        $this->authorizeUtility(Utility::VoicemailDigest);

        return view('utilities.voicemail-digest-history');
    }
}
