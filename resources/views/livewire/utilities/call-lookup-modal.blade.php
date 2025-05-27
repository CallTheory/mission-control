@php
    use App\Models\Stats\Helpers;

    $ck = Helpers::callTypes();
    $tt = Helpers::trackerTypes();
    $st = Helpers::stationTypes();
    $cs = Helpers::callStates();
    $cc = Helpers::compCodes();

@endphp
<x-board-check-modal>

    <x-slot name="content">
        @include('utilities.modal-include')
    </x-slot>

    <x-slot name="buttons">
        <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Close</x-secondary-button>
    </x-slot>

</x-board-check-modal>
