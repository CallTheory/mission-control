<?php

namespace App\Http\Controllers\SendGrid;

use App\Http\Controllers\Controller;
use App\Jobs\InboundRuleMatch;
use App\Models\InboundEmail;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
//use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class ParseController extends Controller
{
    public function __invoke(Request $request, string $api_key): JsonResponse
    {
        if(!Helpers::isSystemFeatureEnabled('inbound-email')) {
            Log::info('[Inbound Email] Feature is disabled');
            return response()->json(['error' => 'not found', 'error_code' => '404'], 404, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        if ($api_key !== hash('md5', config('app.url')) && ! App::environment('local')) {
            Log::info('[Inbound Email] Invalid API Key');
            return response()->json(['error' => 'not found', 'error_code' => '404'], 404, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        $email = new InboundEmail;
        $email->headers = $request->input('headers');
        $email->dkim = $request->input('dkim');
        $email->content_ids = $request->input('content-ids');
        $email->to = $request->input('to');
        $email->from = $request->input('from');
        $email->sender_ip = $request->input('sender_ip');
        $email->envelope = $request->input('envelope');
        $email->attachments = $request->input('attachments');
        $email->subject = $request->input('subject');
        $email->spam_report = $request->input('spam_report');
        $email->spam_score = $request->input('spam_score');
        $email->attachment_info = $request->input('attachment-info');
        $email->charsets = $request->input('charsets');
        $email->text = $request->input('text');
        $email->html = $request->input('html');

        Log::info('[Inbound Email] Trying to save email', $email->only(['id', 'to', 'from', 'subject']));

        try {

            $email->save();

            $attachment_details = json_decode($email->attachment_info, true);

            for($i=1; $i <= $email->attachments; $i++) {

                $file_details = pathinfo($attachment_details["attachment{$i}"]['filename']);
                Log::info('[Inbound Email] Attachment: '.$attachment_details["attachment{$i}"]['filename']);

                //let's only handle attachments that are csv
                if(strtolower($file_details['extension']) === 'csv' && $attachment_details["attachment{$i}"]['type'] === 'text/csv')
                {
                    $filename = Str::slug($file_details['filename']) . '.' . $file_details['extension'];
                    $new_location = "inbound-email/{$email->id}/{$filename}";
                    Log::info('[Inbound Email] Saving attachment to: '.$new_location);
                    Storage::put( $new_location, $request->file("attachment{$i}")->getContent());
                }
                else{
                    Log::info('[Inbound Email] Attachment is not a CSV file', [
                        'extension' => strtolower($file_details['extension']),
                        'mime_type' => $attachment_details["attachment{$i}"]['type']
                    ]);
                }
            }

            Log::info('[Inbound Email] Dispatching InboundRuleMatch job',  $email->only(['id', 'to', 'from', 'subject']));
            InboundRuleMatch::dispatch($email);


        } catch (Exception $e) {
            Log::info('[Inbound Email] Error: '.$e->getMessage());
            //Need to refactor this to work for any company now that we're publishing openly
            /*Mail::raw(json_encode($e->getMessage(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), function ($msg) {
                $msg->subject('Inbound Parse Error ['.config('app.url').']')
                ->to('support@calltheory.com');
            });*/

            return response()->json(['error' => $e->getMessage()], 500, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return response()->json($email->toArray(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
