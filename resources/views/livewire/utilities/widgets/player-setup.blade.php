@php
use App\Models\Stats\Helpers;
@endphp

@script
<script>

    @if(count($recordings) && is_object($recordings[0]))
        checkRecordingAvailability();
    @endif

    @if(Helpers::isSystemFeatureEnabled('screencaptures'))
        @if($screencapture > 0 )
            checkVideoAvailability();
        @endif
    @endif

    function checkVideoAvailability() {

        const videoElement = document.getElementById('screenCaptureVideo');
        const loadingMessage = document.getElementById('screencaptureLoading');
        const videoSrc = `/utilities/screencapture/` + {{ $isCallID }}  + `.mp4`;
        const pollingInterval = 3000;

        if(loadingMessage){
            fetch(videoSrc, { method: 'HEAD', signal: AbortSignal.timeout(pollingInterval) })
                .then(response => {
                    if(response.status === 202){
                        setTimeout(checkVideoAvailability, pollingInterval);
                    }
                    else if (response.ok) {
                        videoElement.src = videoSrc;
                        loadingMessage.classList.add('hidden');
                        videoElement.classList.remove('hidden');
                        videoElement.classList.add('h-full');

                    } else {
                        loadingMessage.remove();
                        videoElement.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error checking video availability:', error);
                    setTimeout(checkVideoAvailability, pollingInterval);
                });
        }
        else{
            setTimeout(checkVideoAvailability, pollingInterval);
        }
    }

    function checkRecordingAvailability() {

        const pollingInterval = 1000; // 1 seconds
        const loadingMessage = document.getElementById('waveformLoading');
        const recordingElement = document.getElementById('waveform-container');
        const recordingUtilitiesElement = document.getElementById('waveform-container-utils');
        const recordingSource = `/utilities/recording/` + {{ $isCallID }}  + `.wav`;

        if(loadingMessage){

            fetch(recordingSource, { method: 'HEAD', signal: AbortSignal.timeout(pollingInterval)})
                .then(response => {

                    //202 is processing
                    if(response.status === 202){
                        setTimeout(checkRecordingAvailability, pollingInterval);
                    }
                    else if (response.ok) {
                        loadingMessage.classList.add('hidden');
                        recordingElement.classList.remove('hidden');
                        recordingUtilitiesElement.classList.remove('hidden');
                        initWavesurfer();
                    } else {
                        loadingMessage.remove();
                        recordingElement.classList.add('hidden');
                        recordingUtilitiesElement.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error checking recording availability:', error);
                    setTimeout(checkRecordingAvailability, pollingInterval);
                });
        }
        else{
            setTimeout(checkRecordingAvailability, pollingInterval);
        }
    }

    function initWavesurfer(){
        let wavesurfer = null;
        let lastSyncTime = 0, lastHighlightSyncTime = 0;
        let syncInterval = 1000, highlightSyncInterval = 150;
        const screenCaptureVideo = document.getElementById('screenCaptureVideo');
        const unlinkPlaybackButton = document.getElementById('unlinkPlaybackButton');
        const linkPlaybackButton = document.getElementById('linkPlaybackButton');
        const customAudioElement = document.getElementById('customAudioElement');
        let wavesurferOptions = {
            container: '#waveform',
            waveColor: '#b8befa',
            progressColor: '#4F46E5',
            scrollParent: false,
            cursorColor: '#000',
            cursorWidth: 2,
            responsive: true,
            normalize: true,
            mediaControls: true,
            barWidth: 2,
            barGap: 1,
            barRadius: 2,
            autoplay: false,
            fillParent: true,
            media: customAudioElement,
        };

        sessionStorage.setItem('sync_audio_video', "false");

        if(linkPlaybackButton)
        {
            linkPlaybackButton.addEventListener('click', linkPlayback);
        }

        if(unlinkPlaybackButton)
        {
            unlinkPlaybackButton.addEventListener('click', unlinkPlayback);
        }

        try{
            wavesurfer = WaveSurfer.create(wavesurferOptions);
            wavesurfer.load(`/utilities/recording/` + {{ $isCallID }}  + `.wav`);
        }
        catch(e){
            console.error('Error initializing wavesurfer:', e);
        }

        if(wavesurfer){
            wavesurfer.on('timeupdate', updateTranscriptionHighlight);
            wavesurfer.on('seeking', updateTranscriptionHighlight);
            wavesurfer.on('interaction', updateTranscriptionHighlight);
            wavesurfer.on('audioprocess', updateTranscriptionHighlight);

            wavesurfer.on('timeupdate', updateLinkedPlayback);
            wavesurfer.on('seeking', updateLinkedPlayback);
            wavesurfer.on('interaction', updateLinkedPlayback);
            wavesurfer.on('audioprocess', updateLinkedPlayback);
        }
        else{
            console.log('Wavesurfer not initialized');
        }

        function updateLinkedPlayback(time){

            if(screenCaptureVideo){
                let sync_audio_video = sessionStorage.getItem('sync_audio_video');
                if(sync_audio_video === "true"){
                    let now = Date.now();
                    if (now - lastSyncTime >= syncInterval) {
                        screenCaptureVideo.currentTime = time;
                        lastSyncTime = now;
                    }
                }
            }
        }

        function refreshPlaybackLinkButtons(){
            unlinkPlaybackButton.classList.toggle('hidden');
            linkPlaybackButton.classList.toggle('hidden');
        }

        function linkPlayback(){
            if(screenCaptureVideo && screenCaptureVideo.hasAttribute("controls")){
                screenCaptureVideo.removeAttribute("controls");
            }
            sessionStorage.setItem('sync_audio_video', "true");
            refreshPlaybackLinkButtons();
        }

        function unlinkPlayback(){
            if(screenCaptureVideo && !screenCaptureVideo.hasAttribute("controls")){
                screenCaptureVideo.setAttribute("controls", "controls");
            }
            sessionStorage.setItem('sync_audio_video', "false");
            refreshPlaybackLinkButtons();
        }

        function updateTranscriptionHighlight(time) {
            let spans = document.getElementById('transcription_text_field');
            if(spans){
                let now = Date.now();
                if (now - lastHighlightSyncTime >= highlightSyncInterval) {
                    let highlightTime = Math.trunc(time * 1000);
                    let addClass = (elem, className) => elem.classList.add(className);
                    let removeClass = (elem, className) => elem.classList.remove(className)
                    let last = undefined;

                    Array.from(spans.getElementsByTagName("SPAN")).forEach((spn) => {
                        let offsetFrom = parseInt(spn.dataset.offsetfrom);
                        let offsetTo = parseInt(spn.dataset.offsetto);

                        if (highlightTime >= offsetFrom && highlightTime <= offsetTo) {
                            addClass(spn, "font-bold");
                            last = spn;
                        } else {
                            removeClass(spn, "font-bold");
                        }
                    });
                    lastHighlightSyncTime = now;
                }
            }
        }
    }
</script>
@endscript
