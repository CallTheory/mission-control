<?php

namespace App\Http\Controllers\API\Agents\InboundEmail;

use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ViewController extends Controller
{
    public function __invoke(Request $request, InboundEmail $email): Response
    {
        return response($email->html ?? $email->text ?? 'No html or text email body found.', 200);
    }
}
