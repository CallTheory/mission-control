@php
    $imageSrc = '/images/mfax.svg';
    $imageAlt = 'mFax by Documo';
@endphp
<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900 ">

        <a wire:click="$toggle('isOpen')"  href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale invert  mr-2" src="{{ $imageSrc }}" alt="{{ $imageAlt }}"> mFax
        </a>
    </div>

    @if($isOpen)
        <div class="">
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">

                    <div class="flex text-4xl text-gray-900 font-extrabold">
                        <img class="h-12 bg-gray-800 rounded-sm  mr-2" src="{{ $imageSrc }}" alt="{{ $imageAlt }}">
                        mFax
                    </div>
                    <br>

                    mFax Enterprise cloud fax for regulated industries

                </x-slot>
                <x-slot name="content">
                    <strong class="">mFax minimum required setup:</strong>
                    <ul class="list-disc list-inside mb-4">
                        <li class="pl-4">Create a new API-only account with mFax by Documo.</li>
                        <li class="pl-4"><a class="font-semibold hover:text-underline" href="https://app.documo.com/settings/api-keys" target="_blank">Generate an API key</a> to be used.</li>
                        <li class="pl-4">Enter the <strong>mFax API key</strong> into the field below.</li>
                    </ul>

                    <strong class="">If using an mFax Cover Page:</strong>
                    <ul class="list-disc list-inside mb-4">
                       <li class="pl-4"><a class="font-semibold hover:text-underline" href="https://app.documo.com/fax/coverpages" target="_blank">Get the Cover Page ID</a> you want to use
                            <br><small class="block pl-6">(try <em>Copy ID to clipboard</em> from your chosen Cover Page menu)</small></li>
                        <li class="pl-4">Enter the <strong>mFax Cover Page ID</strong> into the field below</li>
                        <li class="pl-4">Enter the <strong>Cover Page Sender Name</strong> (required)</li>
                        <li class="pl-4">Enter the <strong>Cover Page Subject Line</strong> (required)</li>
                        <li class="pl-4">Enter the <strong>Cover Page Notes</strong> into the field below (optional)</li>

                    </ul>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="mfax_api_key" value="{{ __('mFax API Key') }}" />
                        <x-input id="mfax_api_key" type="text" class="mt-1 block w-full " wire:model.live="state.mfax_api_key" />
                        <x-input-error for="state.mfax_api_key" class="mt-2" />
                    </div>

                    <div class="rounded-sm p-2 border  border-gray-300">

                        <h5 class="my-2 ">Cover Page Options</h5>

                        <p class="0 text-xs">
                            It's recommended to use a Cover Page when sending to fax machines to avoid PHI being readily visible.
                        </p>

                        <div class="col-span-6 sm:col-span-4 my-2">
                            <x-label for="mfax_cover_page_id" value="{{ __('mFax Cover Page ID (Leave blank to disable cover page)') }}" />
                            <x-input id="mfax_cover_page_id" type="text" class="mt-1 block w-full " wire:model.live="state.mfax_cover_page_id" />
                            <x-input-error for="state.mfax_cover_page_id" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4 my-2">
                            <x-label for="mfax_sender_name" value="{{ __('Cover Page Sender Name') }}" />
                            <x-input id="mfax_sender_name" type="text" class="mt-1 block w-full " wire:model.live="state.mfax_sender_name" />
                            <x-input-error for="state.mfax_sender_name" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4 my-2">
                            <x-label for="mfax_subject" value="{{ __('Cover Page Subject Line') }}" />
                            <x-input id="mfax_subject" type="text" class="mt-1 block w-full " wire:model.live="state.mfax_subject" />
                            <x-input-error for="state.mfax_subject" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4 my-2">
                            <x-label for="mfax_notes" value="{{ __('Cover Page Notes') }}" />
                            <x-input id="mfax_notes" type="text" class="mt-1 block w-full " wire:model.live="state.mfax_notes" />
                            <x-input-error for="state.mfax_notes" class="mt-2" />
                        </div>

                    </div>
                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="saveMFaxDetails"  wire:loading.attr="disabled">
                        Save
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif


</div>
