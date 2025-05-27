<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">

        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale invert mr-2" src="/images/people-praise.png"
                 alt="People Praise">
        </a>
    </div>

    @if($isOpen)
        <div>
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">

                    <div class="flex text-4xl text-gray-900 font-extrabold">
                        <img class="h-12 rounded-sm  mr-2" src="/images/people-praise.png"
                             alt="People Praise">
                    </div>
                </x-slot>
                <x-slot name="content">

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="people_praise_basic_auth_user" value="{{ __('People Praise Basic Auth User') }}" />
                        <x-input id="people_praise_basic_auth_user" type="text" class="mt-1 block w-full " wire:model.live="state.people_praise_basic_auth_user" />
                        <x-input-error for="state.people_praise_basic_auth_user" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-2">
                        <x-label for="people_praise_basic_auth_pass" value="{{ __('People Praise Basic Auth Pass') }}" />
                        <x-input id="people_praise_basic_auth_pass" type="password" class="mt-1 block w-full " wire:model.live="state.people_praise_basic_auth_pass" />
                        <x-input-error for="state.people_praise_basic_auth_pass" class="mt-2" />
                    </div>

                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="savePeoplePraiseDetails" wire:loading.attr="disabled">
                        Save
                    </x-button>

                </x-slot>
            </x-dialog-modal>
        </div>
    @endif


</div>
