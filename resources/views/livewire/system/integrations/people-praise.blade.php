<div>
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800 hover:bg-gray-900">
        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale invert mr-2" src="/images/people-praise.png" alt="People Praise">
        </a>
    </div>

    @if($isOpen)
        <x-dialog-modal wire:model.live="isOpen">
            <x-slot name="title">
                <div class="flex text-4xl text-surface-fg font-extrabold">
                    <img class="h-12 rounded-sm mr-2" src="/images/people-praise.png" alt="People Praise">
                </div>
            </x-slot>
            <x-slot name="content">
                <x-form-field for="people_praise_basic_auth_user" label="{{ __('People Praise Basic Auth User') }}"
                    error-for="state.people_praise_basic_auth_user" wire:model.live="state.people_praise_basic_auth_user" />

                <x-form-field for="people_praise_basic_auth_pass" label="{{ __('People Praise Basic Auth Pass') }}" type="password"
                    error-for="state.people_praise_basic_auth_pass" wire:model.live="state.people_praise_basic_auth_pass" />
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
    @endif
</div>
