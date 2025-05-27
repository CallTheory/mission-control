<div>
    @php
        $imageSrc = '/images/commio.png';
        $imageAlt = 'Commio';
    @endphp
    <div class="col-span-1 flex justify-center py-8 px-8 bg-gray-800  hover:bg-gray-900">

        <a wire:click="$toggle('isOpen')" href="#" class="flex text-4xl text-white font-extrabold">
            <img class="h-12 rounded-sm grayscale mr-2" src="{{ $imageSrc }}"
                 alt="{{ $imageAlt }}">
        </a>
    </div>

    @if($isOpen)
        <div>
            <x-dialog-modal wire:model.live="isOpen">
                <x-slot name="title">

                    <div class="flex text-4xl text-gray-900 font-extrabold">
                        <img class="h-12 invert rounded-sm  mr-2" src="{{ $imageSrc }}"
                             alt="{{ $imageAlt }}">
                    </div>
                </x-slot>
                <x-slot name="content">

                    <x-alert-info title="Coming Soon" description="Things aren't quite finished here. Check back later!" />

                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2 hidden" wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                        Save
                    </x-button>

                </x-slot>
            </x-dialog-modal>
        </div>
    @endif


</div>


