@php
    $css = 'w-full';
    use App\Models\Stats\Helpers;
@endphp

    @if((count($recordings) && is_object($recordings[0])) || $screencapture > 0 )
        <div class="w-3/4 print:hidden" wire:ignore>
            <div class="flex">
                @if(Helpers::isSystemFeatureEnabled('screencaptures'))
                    @if($screencapture > 0 )
                        @php
                            $css = 'w-3/4';
                        @endphp
                        <div class="block w-1/4 mx-0">
                            <video id="screenCaptureVideo"
                                   class="p-0 rounded shadow border border-gray-300 bg-gray-200 hidden"
                                   controls controlsList="nodownload"
                                   oncontextmenu="return false;">
                                Your browser does not support the video tag.
                            </video>
                            <div id="screencaptureLoading" class="text-gray-400 text-xs text-center">
                                Loading...
                            </div>
                        </div>

                    @endif
                @endif

                @if((count($recordings) && is_object($recordings[0])))
                    <div id="waveform-container" class="{{ $css }} mx-2 hidden min-h-full">
                        <div id="waveform"
                             class="p-0 m-0 rounded shadow border border-gray-300 bg-gray-200">
                            <noscript>Playback requires javascript</noscript>
                        </div>
                        <audio id="customAudioElement" class="w-full" oncontextmenu="return false;" controls controlsList="nodownload"></audio>
                    </div>
                    <div id="waveformLoading" class="block {{ $css }} mx-2 h-full text-gray-400 text-xs text-center">
                        Loading...
                    </div>
                @else
                    <div class="w-3/4 print:hidden">
                        <div class="text-gray-400 text-left mx-auto max-w-xl my-2">
                            No Call Recording
                        </div>
                    </div>
                @endif
            </div>
            <div id="waveform-container-utils" class="block w-full flex place-content-end ml-auto mr-0 hidden">
                @if(Helpers::isSystemFeatureEnabled('screencaptures'))
                    @if($screencapture > 0 )
                        <button id="linkPlaybackButton" class="text-xs text-gray-300 hover:text-gray-500 mx-2">Link Playback</button>
                        <button id="unlinkPlaybackButton" class="text-xs text-gray-300 hover:text-gray-500 mx-2 hidden">Unlink Playback</button>
                    @endif
                @endif

                <button class="text-xs text-gray-300 hover:text-gray-500 mx-2" wire:click="clearCallCache">Clear Cache</button>

            </div>
        </div>

    @else
        <div class="w-3/4 print:hidden">
            <div class="p-2 text-gray-400 mx-2 text-center">
                No Call Recordings or Screen Capture found.
            </div>
        </div>
    @endif

