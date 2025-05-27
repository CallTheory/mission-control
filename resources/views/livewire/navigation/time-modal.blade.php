<x-general-modal>
    <x-slot name="title">
        Time Parameters
    </x-slot>

    <x-slot name="content" >
        Time Modal
    </x-slot>

    <x-slot name="buttons">
        <div class="mt-4">
            <x-button class="ml-2" wire:click="$dispatch('closeModal')">Update</x-button>
            <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Close</x-secondary-button>
        </div>
    </x-slot>

</x-general-modal>
