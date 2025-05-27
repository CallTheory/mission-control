<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Stats\Clients\Greetings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use App\Models\Stats\Helpers;

class ClientGreetingController extends Controller
{
    /**
     * @param Request $request
     * @param $greetingID
     * @return Response
     * @throws Exception
     */
    public function __invoke(Request $request, $greetingID): Response
    {

        if($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }

        try{
            $greeting = new Greetings(['greetingID' => $greetingID]);
        }
        catch(Exception $e){
            abort(404, 'Greeting not found');
        }

        if(Helpers::allowedAccountAccess(
            $greeting->ClientNumber,
            $greeting->BillingCode ?? '',
            $request->user()->currentTeam->allowed_accounts ?? '',
            $request->user()->currentTeam->allowed_billing ?? ''
        ) !== true){
            abort(403, 'Forbidden account number or billing code');
        }

        $vf = Helpers::voiceFormats();

        $format = Str::lower($vf[$greeting->Format]);
        if($format == 'wav'){
            $extension = 'wav';
        }
        elseif($format == 'wav16'){
            $extension = 'wav';
        }
        elseif($format == 'ulaw'){
            $extension = 'ulaw';
        }
        elseif($format == 'gsm'){
            $extension = 'gsm';
        }
        else{
            abort(400, 'Unknown voice format supplied');
        }

        $greetingData = Redis::get("greetings-{$greetingID}.wav");

        if (! $greetingData) {
            $greetingPath[$greetingID] = "greetings/{$greetingID}/{$greetingID}.{$extension}";
            Storage::put($greetingPath[$greetingID], $greeting->Greeting);

            if($extension == 'ulaw'){
                try {
                    $process = new Process(array_merge(
                            ['sox'],
                            ['-e', 'mu-law'],
                            ['-t', 'ul'],
                            [storage_path("app/{$greetingPath[$greetingID]}")],
                            ['-b', 1],
                            ['-c', 1],
                            ['-r', 16000],
                            [storage_path("app/greetings/{$greetingID}.wav")])
                    );
                    $process->run();
                } catch (Exception $e) {
                    Storage::delete("greetings/{$greetingID}.wav");
                    Storage::delete($greetingPath[$greetingID] );
                    Storage::deleteDirectory("greetings/{$greetingID}");
                    if(App::environment('local')){
                        throw $e;
                    }
                    else {
                        abort(500, 'Unable to process file');
                    }
                }
            }
            elseif($extension == 'gsm'){
                try {
                    $process = new Process(array_merge(
                            ['sox'],
                            ['-e', 'gsm-full-rate'],
                            ['-t', 'gsm'],
                            [storage_path("app/{$greetingPath[$greetingID]}")],
                            ['-b', 1],
                            ['-c', 1],
                            ['-r', 16000],
                            [storage_path("app/greetings/{$greetingID}.wav")])
                    );
                    $process->run();
                } catch (Exception $e) {
                    Storage::delete("greetings/{$greetingID}.wav");
                    Storage::delete($greetingPath[$greetingID] );
                    Storage::deleteDirectory("greetings/{$greetingID}");
                    if(App::environment('local')){
                        throw $e;
                    }
                    else {
                        abort(500, 'Unable to process file');
                    }
                }
            }
            else{
                try {
                    $process = new Process(array_merge(
                            ['sox'],
                            [storage_path("app/{$greetingPath[$greetingID]}")],
                            ['-b', 1],
                            ['-c', 1],
                            ['-r', 16000],
                            [storage_path("app/greetings/{$greetingID}.wav")])
                    );
                    $process->run();
                } catch (Exception $e) {
                    Storage::delete("greetings/{$greetingID}.wav");
                    Storage::delete($greetingPath[$greetingID] );
                    Storage::deleteDirectory("greetings/{$greetingID}");
                    if(App::environment('local')){
                        throw $e;
                    }
                    else {
                        abort(500, 'Unable to process file');
                    }
                }

            }

            if (!$process->isSuccessful()) {
                Storage::delete("greetings/{$greetingID}.wav");
                Storage::delete($greetingPath[$greetingID] );
                Storage::deleteDirectory("greetings/{$greetingID}");
                if(App::environment('local')){
                    throw new ProcessFailedException($process);
                }
                else {
                    abort(500, 'Unable to process file');
                }
            }

            if (Storage::exists("greetings/{$greetingID}.wav")) {
                $greetingData = Storage::get("greetings/{$greetingID}.wav");
                Redis::setEx("greetings-{$greetingID}.wav", 86400, $greetingData);
                Storage::delete("greetings/{$greetingID}.wav");
                Storage::delete($greetingPath[$greetingID] );
                Storage::deleteDirectory("greetings/{$greetingID}");
            }
            else{
                Storage::delete("greetings/{$greetingID}.wav");
                Storage::delete($greetingPath[$greetingID] );
                Storage::deleteDirectory("greetings/{$greetingID}");
                abort(404, 'No greeting found');
            }
        }

        $headers = [
            'content-type' => 'audio/wav',
            'content-disposition' => 'attachment:filename="' . $greetingID . '.wav"',
            'content-length' => strlen($greetingData),
        ];
        return response($greetingData, 200, $headers);
    }
}
