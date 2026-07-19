<?php

namespace App\Http\Controllers\API\Agents\InboundEmail;

use App\Http\Controllers\Concerns\VerifiesSharedSecret;
use App\Http\Controllers\Controller;
use App\Mail\ForwardInboundEmail;
use App\Models\InboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ForwardController extends Controller
{
    use VerifiesSharedSecret;

    public function __invoke(Request $request, InboundEmail $email): JsonResponse
    {
        if (! $this->sharedSecretMatches($request->input('api_key'), config('services.inbound_email.forward_secret'))) {
            return response()->json(['success' => 'false', 'error' => 'unauthorized'], 403, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 'false', 'errors' => $validator->errors()], 400, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        Mail::send(new ForwardInboundEmail($email, $request->input('email')));

        return response()->json(['success' => 'true'], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
