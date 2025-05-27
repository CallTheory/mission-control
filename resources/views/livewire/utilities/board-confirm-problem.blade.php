@php
    use App\Models\Stats\Helpers;
@endphp
<x-modal>
    <x-slot name="title">
        Confirm issue with message {{ $msgId }}
    </x-slot>

    <x-slot name="content">

        @include('utilities.modal-include')

        <div class="col-span-6 sm:col-span-4">
            <x-label for="category" value="{{ __('Category') }}" />
            <select wire:model.live="state.category" id="category" class="mt-1 block w-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ">
                @foreach(Helpers::boardCheckCategories() as $key => $category )
                    <option value="{{ $key }}">{{ $category }}</option>
                @endforeach
            </select>
            <x-input-error for="state.category" class="mt-2" />
        </div>


        <div class="col-span-6 sm:col-span-4">
            <x-label for="comments" value="{{ __('Comments') }}" />
            <textarea wire:model.live="state.comments" id="comment" class="mt-1 block w-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow "></textarea>
            <x-input-error for="state.comments" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="buttons">
        <x-button wire:click="confirmProblem()">
            {{ __('Confirm Problem') }}
        </x-button>

        <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Cancel</x-secondary-button>
    </x-slot>
</x-modal>
