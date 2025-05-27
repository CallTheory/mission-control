<x-general-modal>
    <x-slot name="title">
        Date Range Parameters
    </x-slot>

    <x-slot name="content" >

        <div class="col-span-6 sm:col-span-4">
            <x-label for="start_date" value="{{ __('Start Date') }}" />
            <x-input wire:model.live="start_date" id="start_date" class="mt-1 block w-full px-2 py-1 start_date" />
            <x-input-error for="start_date" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="end_date" value="{{ __('End Date') }}" />
            <x-input wire:model.live="end_date" id="end_date" class="mt-1 block w-full px-2 py-1 end_date" />
            <x-input-error for="end_date" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="buttons">
        <div class="mt-4">
            <x-button class="ml-2" wire:click="$dispatch('closeModal')">Update</x-button>
            <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Close</x-secondary-button>
        </div>
    </x-slot>


</x-general-modal>
