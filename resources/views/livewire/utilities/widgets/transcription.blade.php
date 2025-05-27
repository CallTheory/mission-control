@if(count($recordings) && is_object($recordings[0]))
    @if($transcription)

        <div class="my-4 flex" wire:ignore>
            <div class="group w-full bg-gray-100 border border-gray-300 rounded px-2 pt-6 pb-4 relative" id="transcription_text_field">
                <label for="transcription_text_field"
                       class="absolute left-0 top-0 text-xs text-gray-500 group-hover:text-indigo-500 m-2 transform transition duration-700">
                    <button id="toggleTranscriptionAccordionButton">
                        Transcription
                    </button>
                </label>
                {!! $transcription['html'] !!}
            </div>
        </div>

        @script
        <script>
            let transcriptionCheckInterval = setInterval(() => {
                const toggleTranscriptionAccordionButton = document.getElementById('toggleTranscriptionAccordionButton');
                if(toggleTranscriptionAccordionButton) {
                    console.log('Removing interval and setting up toggle');
                    toggleTranscriptionAccordionButton.addEventListener('click', transcriptionAccordionToggle);
                    clearInterval(transcriptionCheckInterval);
                }
            }, 1000);

            function transcriptionAccordionToggle(){
                let spans = document.getElementById('transcription_text_field');
                if(spans){
                    Array.from(spans.getElementsByTagName("SPAN")).forEach((spn) => {
                        spn.classList.toggle("block");
                    });
                    this.classList.toggle("text-indigo-500");
                    this.classList.toggle("font-bold");
                }
            }
        </script>
        @endscript
    @endif
@endif
