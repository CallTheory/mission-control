@php
    $imageSrc = '/images/mfax.svg';
    $imageAlt = 'mFax by Documo';
@endphp
<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">
        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale invert mr-2" src="{{ $imageSrc }}" alt="{{ $imageAlt }}"> mFax
        </a>
    </div>

    @if($isOpen)
        <x-dialog-modal wire:model.live="isOpen">
            <x-slot name="title">
                <div class="flex text-4xl text-surface-fg font-extrabold">
                    <img class="h-12 bg-gray-800 rounded-sm mr-2" src="{{ $imageSrc }}" alt="{{ $imageAlt }}">
                    mFax
                </div>
                <br>
                mFax Enterprise cloud fax for regulated industries
            </x-slot>
            <x-slot name="content">
                <strong>mFax minimum required setup:</strong>
                <ul class="list-disc list-inside mb-4">
                    <li class="pl-4">Create a new API-only account with mFax by Documo.</li>
                    <li class="pl-4"><a class="font-semibold hover:underline" href="https://app.documo.com/settings/api-keys" target="_blank">Generate an API key</a> to be used.</li>
                    <li class="pl-4">Enter the <strong>mFax API key</strong> into the field below.</li>
                </ul>

                <strong>If using an mFax Cover Page:</strong>
                <ul class="list-disc list-inside mb-4">
                    <li class="pl-4"><a class="font-semibold hover:underline" href="https://app.documo.com/fax/coverpages" target="_blank">Get the Cover Page ID</a> you want to use
                        <br><small class="block pl-6">(try <em>Copy ID to clipboard</em> from your chosen Cover Page menu)</small></li>
                    <li class="pl-4">Enter the <strong>mFax Cover Page ID</strong> into the field below</li>
                    <li class="pl-4">Enter the <strong>Cover Page Sender Name</strong> (required)</li>
                    <li class="pl-4">Enter the <strong>Cover Page Subject Line</strong> (required)</li>
                    <li class="pl-4">Enter the <strong>Cover Page Notes</strong> into the field below (optional)</li>
                </ul>

                <x-form-field for="mfax_api_key" label="{{ __('mFax API Key') }}"
                    error-for="state.mfax_api_key" wire:model.live="state.mfax_api_key" />

                <div class="rounded-sm p-2 border border-border">
                    <h5 class="my-2">Cover Page Options</h5>

                    <p class="text-xs text-muted">
                        It's recommended to use a Cover Page when sending to fax machines to avoid PHI being readily visible.
                    </p>

                    <x-form-field for="mfax_cover_page_id" label="{{ __('mFax Cover Page ID (Leave blank to disable cover page)') }}"
                        error-for="state.mfax_cover_page_id" wire:model.live="state.mfax_cover_page_id" />

                    <x-form-field for="mfax_sender_name" label="{{ __('Cover Page Sender Name') }}"
                        error-for="state.mfax_sender_name" wire:model.live="state.mfax_sender_name" />

                    <x-form-field for="mfax_subject" label="{{ __('Cover Page Subject Line') }}"
                        error-for="state.mfax_subject" wire:model.live="state.mfax_subject" />

                    <x-form-field for="mfax_notes" label="{{ __('Cover Page Notes') }}"
                        error-for="state.mfax_notes" wire:model.live="state.mfax_notes" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                    Cancel
                </x-secondary-button>

                <x-button class="ml-2" wire:click="saveMFaxDetails" wire:loading.attr="disabled">
                    Save
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
