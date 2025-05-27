@php
use Illuminate\Support\Facades\Session;
@endphp

<div class="block w-full">
    <form wire:submit.prevent="userLookupCall" class="bg-white border border-gray-300 shadow rounded">
        <div class="px-4 py-5 sm:p-6 ">
            <div class="block w-100">
                <x-input value="{{ $isCallID ?? Session::get('searchTerm') ?? '' }}" required id="isCallID" type="text" class="mt-1 block w-full " wire:model.defer="isCallID" />
                <x-input-error for="isCallID" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center justify-end px-4 py-3 bg-gray-50 text-right sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">

            <span class="mr-3  text-sm" wire:loading>
                {{ __('Looking up call...') }}
            </span>

            <x-action-message class="mr-3 " on="search">
                {{ __('Search complete.') }}
            </x-action-message>

            <x-button class="print:hidden">
                {{ __('Search') }}
            </x-button>
        </div>
    </form>
</div>
