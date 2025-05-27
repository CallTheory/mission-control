<div>
    <x-button wire:click="$toggle('isOpen')">
        Create
    </x-button>

    @if($isOpen)
        <div class="absolute z-100">
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">
                    <div class="flex text-2xl text-gray-900 font-bold">
                        Better Email &middot; New
                    </div>
                </x-slot>
                <x-slot name="content">

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="client_number" class="font-semibold" value="{{ __('Client Number') }}" />
                        <x-input id="client_number" type="text" class="mt-1 block w-full " wire:model.defer="state.client_number" />
                        <small class="text-xs text-gray-400">The Intelligent Series Client Number</small>
                        <x-input-error for="state.client_number" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="recipients" class="font-semibold" value="{{ __('Recipients') }}" />
                        <textarea id="recipients" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model.defer="state.recipients"></textarea>
                        <small class="text-xs text-gray-400">One email address per line</small>
                        <x-input-error for="state.recipients" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="subject" class="font-semibold" value="{{ __('Subject') }}" />
                        <x-input id="subject" type="text" class="mt-1 block w-full " wire:model.defer="state.subject" />
                        <small class="text-xs text-gray-400">The subject line for the email</small>
                        <x-input-error for="state.subject" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="title" class="font-semibold" value="{{ __('Title') }}" />
                        <x-input id="title" type="text" class="mt-1 block w-full " wire:model.defer="state.title" />
                        <small class="text-xs text-gray-400">The title of the email body</small>
                        <x-input-error for="state.title" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="description" class="font-semibold" value="{{ __('Description') }}" />
                        <x-input id="description" type="text" class="mt-1 block w-full " wire:model.defer="state.description" />
                        <small class="text-xs text-gray-400">The description is displayed under the title and in the email preview</small>
                        <x-input-error for="state.description" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="report_metadata" class="font-semibold" value="{{ __('Include Report Metadata') }}" />
                        <select id="report_metadata" class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.report_metadata">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <small class="text-xs text-gray-400">Includes a table of metadata about the report</small>
                        <x-input-error for="state.report_metadata" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="message_history" class="font-semibold" value="{{ __('Include Message History') }}" />
                        <select id="message_history" class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.message_history">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <small class="text-xs text-gray-400">This can also be controlled via Amtelco SendMessages element and Schedule SendMessages</small>
                        <x-input-error for="state.message_history" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="theme" class="font-semibold" value="{{ __('Theme') }}" />
                        <select id="theme" name="theme"  class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.theme">
                            <option value="standard">Standard</option>
                            <option value="standard">Dark</option>
                        </select>
                        <small class="text-xs text-gray-400">Select one of our standard templates or contact us for a custom-themed email template</small>
                        <x-input-error for="state.theme" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo" class="font-semibold" value="{{ __('Logo Url') }}" />
                        <x-input id="logo" type="text" class="mt-1 block w-full " wire:model.defer="state.logo" />
                        <small class="text-xs text-gray-400">The logo to displayed at the top of the email</small>
                        <x-input-error for="state.logo" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo_alt" class="font-semibold" value="{{ __('Logo Alt Text') }}" />
                        <x-input id="logo_alt" type="text" class="mt-1 block w-full " wire:model.defer="state.logo_alt" />
                        <small class="text-xs text-gray-400">The alt text for the logo</small>
                        <x-input-error for="state.logo_alt" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo_link" class="font-semibold" value="{{ __('Logo Link') }}" />
                        <x-input id="logo_link" type="text" class="mt-1 block w-full " wire:model.defer="state.logo_link" />
                        <small class="text-xs text-gray-400">The link to visit if they click the logo</small>
                        <x-input-error for="state.logo_link" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="button_text" class="font-semibold" value="{{ __('Button Text') }}" />
                        <x-input id="button_text" type="text" class="mt-1 block w-full " wire:model.defer="state.button_text" />
                        <small class="text-xs text-gray-400">The text for the button at the end of the email</small>
                        <x-input-error for="state.button_text" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="button_link" class="font-semibold" value="{{ __('Button Link') }}" />
                        <x-input id="button_link" type="text" class="mt-1 block w-full " wire:model.defer="state.button_link" />
                        <small class="text-xs text-gray-400">The link (both https:// and mailto:// are supported)</small>
                        <x-input-error for="state.button_link" class="mt-2" />
                    </div>

                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="addBetterEmail" wire:loading.attr="disabled">
                        Save
                    </x-button>

                </x-slot>
            </x-dialog-modal>
        </div>
    @endif
</div>
