<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Mail\PrettyLog;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PreviewBetterEmailsThemeController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request, $theme): PrettyLog
    {
        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }

        $file_name = $request->get('example_file', 'messages 5520 06042024-070000.txt');
        $parsedLog = Helpers::parseMessageLog("better-emails/{$file_name}");

        $email_details = [
            'theme' => $theme,
            'include' => [
                'report_metadata' => $request->get('report_metadata'),
                'message_history' => $request->get('message_history'),
            ],
            'title' => $request->get('title') ?? 'Messages from your call center',
            'description' => $request->get('description') ?? 'Your daily message log',
            'envelope' => $parsedLog['envelope'],
            'log' => $parsedLog['log'],
            'subject' => $request->get('subject') ?? 'Messages from your call center',
            'logo' => [
                'src' => $request->get('logo') ?? url('/images/mission-control.png'),
                'alt' => $request->get('logo_alt') ?? 'Mission Control',
                'link' => $request->get('logo_link') ?? url('/'),
            ],
            'button' => [
                'text' => $request->get('button_text') ?? 'Have questions?',
                'link' => $request->get('button_link') ?? 'mailto:support@calltheory.com',
            ],
            'canspam' => [
                'address' => $request->get('canspam_address') ?? '3989 Broadway',
                'address2' => $request->get('canspam_address2') ?? '',
                'city' => $request->get('canspam_city') ?? 'Grove City',
                'state' => $request->get('canspam_state') ?? 'OH',
                'postal' => $request->get('canspam_postal') ?? '43123',
                'country' => $request->get('canspam_country') ?? 'US',
                'email' => $request->get('canspam_email') ?? 'support@calltheory.com',
                'phone' => $request->get('canspam_phone') ?? '614-555-1234',
                'company' => $request->get('canspam_company') ?? 'Mission Control',
            ],
            'unsubscribe_link' => URL::signedRoute('email-unsubscribe', ['eid' => 1, 'email' => 'test@test.com']),
        ];

        try {
            $email = new PrettyLog($email_details);
        } catch (Exception $e) {
            abort(500);
        }

        return $email;
    }
}
