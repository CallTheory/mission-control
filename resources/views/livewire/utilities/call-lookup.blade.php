@php
use App\Models\Stats\Helpers;
@endphp
<div class="min-w-full w-full" wire:poll.10000ms.visible="refreshTranscriptionStatus">

    <div class="mb-4 mx-auto block">

        @if(!is_null($details ?? null))
            <div class="min-w-full mx-2">
                <div class="w-full flex space-x-2">

                    @include('livewire.utilities.widgets.info')
                    @include('livewire.utilities.widgets.player')
                </div>
            </div>
        @endif

    </div>
    @if(Helpers::isSystemFeatureEnabled('transcription'))
        <div>
            @include('livewire.utilities.widgets.transcription')
        </div>
    @endif

    @if(!is_null($details ?? null))

        <div class="flex w-full space-x-2">
            @include('livewire.utilities.widgets.tracker')
        </div>

        <div class="flex w-full space-x-2">
            @include('livewire.utilities.widgets.messages')
            @include('livewire.utilities.widgets.history')
        </div>

        <div class="flex w-full space-x-2 clear-both">
            @include('livewire.utilities.widgets.details')
            @include('livewire.utilities.widgets.call-metrics')
        </div>

    @endif

    @include('livewire.utilities.widgets.player-setup')

</div>



