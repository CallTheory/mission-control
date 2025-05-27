@php
    use App\Models\Stats\Helpers;
@endphp

<x-board-check-modal>
    <x-slot name="title">
        Review Message {{ $msgId }} (Call: {{ $isCallID }})
    </x-slot>

    <x-slot name="content" >

        <div class=" mx-4">
            Please verify the message looks correct and accurate according to company standards and press the <strong>Confirm Message</strong> below.
        </div>

        <hr class="border border-gray-300"/>

        <livewire:utilities.call-lookup lazy :isCallID="$isCallID" />

        <hr class="border border-gray-300"/>

        <div class="mx-4">
            <x-button class=" " wire:click="confirmMessage()">
                {{ __('Mark Message OK') }}
            </x-button>
        </div>

        <hr class="my-4 border border-gray-300" />

        <div class="mx-4 mt-12">

            If there is a problem with the message, note the details below and then click <strong>Escalate to Supervisor</strong>.

            <div class="col-span-6 sm:col-span-4 my-2">

                <x-label for="category" value="{{ __('Category') }}" />
                <select wire:model.defer="state.category" id="category" class="mt-1 block w-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ">
                    <option></option>
                    @foreach(Helpers::boardCheckCategories() as $key => $category )
                        <option value="{{ $key }}">{{ $category }}</option>
                    @endforeach
                </select>
                <x-input-error for="state.category" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4  my-2">
                <x-label for="comments" value="{{ __('Comments') }}" />
                <textarea wire:model.defer="state.comments" id="comment" class="mt-1 block w-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow "></textarea>
                <x-input-error for="state.comments" class="mt-2" />
            </div>

            @if(isset($state['agents']) && is_object($state['agents'][0]))
                <div class="col-span-6 sm:col-span-4  my-2">
                    <x-label for="agtId" value="{{ __('Responsible Agent') }}" />
                    <select wire:model.defer="state.agtId" id="agtId" class="mt-1 block w-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ">
                        <option></option>
                        @foreach($state['agents'] as $agent )
                            <option value="{{$agent->agtId }}">{{ $agent->Name }} ({{$agent->Initials }})</option>
                        @endforeach

                    </select>
                    <x-input-error for="state.agtId" class="mt-2" />
                </div>
            @endif

        </div>

    </x-slot>

    <x-slot name="buttons">
        <div class="mt-4">

            <x-danger-button class="ml-2" wire:click="confirmProblem()">
                {{ __('Confirm Flagged Issue') }}
            </x-danger-button>

            <x-secondary-button class="ml-2" wire:click="$dispatch('closeModal')">Cancel</x-secondary-button>

        </div>


    </x-slot>
</x-board-check-modal>
