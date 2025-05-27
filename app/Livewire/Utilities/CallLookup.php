<?php

namespace App\Livewire\Utilities;

use App\Livewire\Navigation\Search;
use App\Models\Stats\Calls\Keywords;
use App\Models\Stats\Calls\Revisions;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Exception;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Renderer\RendererConstant;
use Livewire\Component;
use App\Models\Stats\Calls\Call;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class CallLookup extends Component
{
    public mixed $isCallID = null;

    public $transcription, $details, $messages, $history,
        $statistics, $recordings, $agents, $clients, $tracker,
        $screencapture, $timezone, $ck, $tt, $st, $cs, $cc,
        $messageKeywords, $revisions;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm text-gray-500">
           Loading call details...one moment, please.
        </div>
        HTML;
    }

    public function clearCallCache(): void
    {
        //forces the recording to be re-processes by sox and whisper.cpp and re-saved into redis
        Redis::del("{$this->isCallID}.wav");
        Redis::del("{$this->isCallID}.json");
        Redis::del("{$this->isCallID}.mp4");
        $this->redirect("/utilities/call-lookup/{$this->isCallID}");
    }

    public function refreshTranscriptionStatus(): void
    {
        if($this->transcription === null){
            try {
               $this->lookupCall();
            } catch (Exception $e) {
                $this->transcription = null;
                $this->skipRender();
            }
        }
    }

    /**
     * @throws Exception
     */
    public function lookupCall(): void
    {

        $this->resetErrorBag();

        $this->validate([
            'isCallID' => 'required|numeric|between:1,10000000000',
        ], [
            'isCallID' => $this->isCallID ?? null,
        ]);

        $this->dispatch('searchTermUpdate', $this->isCallID)->to(Search::class);

        try {

            $call = new Call(['ISCallId' => $this->isCallID]);
            Session::put('searchTerm', $this->isCallID ?? '');
        } catch (Exception $e) {
            $this->addError('isCallID', 'Unable to load call from database');
            return;
        }

        try{
            $keywords = new Keywords([
                'ISCallId' => $this->isCallID,
                'savedCallId' => $this->isCallID
            ]);
            $this->messageKeywords = $keywords->details() ?? [];
        }catch(Exception $e){
            if(App::environment('local')){ throw $e; }
            $this->messageKeywords = [];
        }

        $this->details = $call->details();
        $this->messages = $call->messages();
        $this->history = $call->history();
        $this->statistics = $call->statistics();
        $this->recordings = $call->recordings();
        $this->agents = $call->agents();
        $this->clients = $call->clients();
        $this->tracker = $call->tracker();
        $this->screencapture = count($call->screenCapture());
        $this->transcription = $call->transcription();
        $this->getSummaryAndDiffs();


    }
    public function mount(): void
    {
        $this->timezone = Settings::first()->switch_data_timezone ?? 'UTC';
        $this->ck = Helpers::callTypes();
        $this->tt = Helpers::trackerTypes();
        $this->st = Helpers::stationTypes();
        $this->cs = Helpers::callStates();
        $this->cc = Helpers::compCodes();
    }

    protected function clear(): void
    {
        $this->details = null;
        $this->messages = null;
        $this->history = null;
        $this->statistics = null;
        $this->recordings =null;
        $this->agents = null;
        $this->clients = null;
        $this->tracker = null;
        $this->screencapture = null;
        $this->transcription = null;

    }

    /**
     * @throws Exception
     */
    protected function getSummaryAndDiffs(): void
    {
        $msgRevisions = new Revisions(['ISCallId' => $this->isCallID, 'savedCallID' => $this->isCallID]);

        //HTML renderers: Combined, Inline, JsonHtml, SideBySide
        $rendererName = 'Inline';

        $differOptions = [
            'context' => Differ::CONTEXT_ALL,
            'ignoreCase' => false,
            'ignoreLineEnding' => false,
            'ignoreWhitespace' => false,
            'lengthLimit' => 2000,
            'fullContextIfIdentical' => false,
        ];

        $rendererOptions = [
            // how detailed the rendered HTML in-line diff is? (none, line, word, char)
            'detailLevel' => 'line',
            'language' => 'eng',
            'lineNumbers' => true,
            'separateBlock' => true,
            'showHeader' => true,
            'spacesToNbsp' => false,
            'tabSize' => 4,
            'mergeThreshold' => 0.8,
            'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
            'outputTagAsString' => false,
            'jsonEncodeFlags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            'wordGlues' => [' ', '-'],
            'resultForIdenticals' => null,
            'wrapperClasses' => ['diff-wrapper'],
        ];

        $revisions = [];

        foreach($msgRevisions->details() as $msgRevision){
            if($msgRevision->XmlMessage !== null){
                $msgRevisionArray = Helpers::parseXmlMessage($msgRevision->XmlMessage);


                foreach($msgRevisionArray['messages'] as $index => $messages){

                    $revisions[$index]['timestamp'] = $messages['@attributes']['timeStamp'];

                    if(isset($messages['@attributes']['summary']))
                    {
                        $revisions[$index]['revisions'] = $messages['@attributes']['summary'];


                    }
                    else{
                        $revisions[$index]['revisions'] = $messages['@attributes']['autoSummary'] ?? '';
                    }
                }

                $firstAnnotation = 0;

                foreach($msgRevisionArray['annotations'] as $index => $annotations){

                    if(isset($annotations[$firstAnnotation]) && isset($annotations['@attributes'])){
                        $revisions[$index]['annotations'] = [
                            'stamp' => $annotations['@attributes']['stamp'] ?? '',
                            'message' => $annotations[$firstAnnotation],
                        ];
                        $firstAnnotation++;
                    }
                }


                foreach($msgRevisionArray['fields'] as $index => $fields){
                    $revisions[$index]['fields'] = $fields;
                }
            }
        }

        $this->revisions = [];
        for($i=0;$i<count($revisions);$i++){
            $this->revisions[$i]['fields'] = $revisions[$i]['fields'] ?? [];
            $this->revisions[$i]['annotations'] = $revisions[$i]['annotations'] ?? [];
            $this->revisions[$i]['timestamp'] = $revisions[$i]['timestamp'] ?? '';
            $this->revisions[$i]['summary'] = $revisions[$i]['revisions'] ?? [];
            if($i!=0){
                $this->revisions[$i]['diff'] = DiffHelper::calculate($revisions[$i-1]['revisions'] ?? '', $revisions[$i]['revisions'] ?? '', 'Inline', $differOptions, $rendererOptions);
                $this->revisions[$i]['diff2'] = DiffHelper::calculate($revisions[$i-1]['revisions'] ?? '', $revisions[$i]['revisions'] ?? '', 'Combined', $differOptions, $rendererOptions);
                $this->revisions[$i]['diff3'] = DiffHelper::calculate($revisions[$i-1]['revisions'] ?? '', $revisions[$i]['revisions'] ?? '', 'SideBySide', $differOptions, $rendererOptions);
            }
            else{
                $this->revisions[$i]['diff'] = '';
                $this->revisions[$i]['diff2'] = '';
                $this->revisions[$i]['diff3'] = '';
            }
        }
    }

    /**
     * @throws Exception
     */
    public function render(): View
    {
        $this->lookupCall();
        return view('livewire.utilities.call-lookup');
    }
}
