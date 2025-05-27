<x-modal>
    <x-slot name="title">
        Mark message {{ $msgId }} OK?
    </x-slot>

    <x-slot name="content" >
        <div class="">
            Please <strong>confirm</strong> the message looks correct and press the Mark Message OK button below.
        </div>

        @include('utilities.modal-include')

    </x-slot>

    <x-slot name="buttons">
        <x-button wire:click="confirmMessage()">
            {{ __('Mark Message OK') }}
        </x-button>

        <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Cancel</x-secondary-button>


    </x-slot>
</x-modal>
