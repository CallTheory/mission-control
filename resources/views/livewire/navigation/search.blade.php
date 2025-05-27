<div>
    @if($enabled)
        <div class="relative mt-2 flex items-center">
            <x-input type="text" wire:model.defer="searchTerm" wire:keydown.enter="search" name="searchTerm" id="searchTerm"
                     class="-my-2  transform transition duration-500 ease-in-out text-xs"
                     placeholder="Lookup ISCallId..." />
            @if($searchTerm)
                <div class="absolute right-0 flex py-1.5 pr-1.5">
                    <button wire:click="clearSearchTerm"
                            class="hover:text-red-500 inline-flex items-center rounded
                            border border-gray-300 px-1 font-sans text-xs text-gray-400
                            my-auto mr-2">&times;</button>
                </div>
            @endif
        </div>
        <x-input-error for="searchTerm" class="inline py-2 my-2" />
    @endif
</div>
