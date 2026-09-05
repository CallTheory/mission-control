<div class="w-full">
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>
    <div class="container w-full flex">
        <div class="mx-2">

            <x-button
                wire:click="getRecent"
                class="px-2 py-1 transition transform duration-700 ease-in-out"
                    type="button">
                 <span
                     wire:loading.remove
                     wire:target="getRecent">
                     Fill Records
                </span>
                <span
                    wire:loading
                    wire:target="getRecent">
                    Filling...
                </span>

            </x-button>
        </div>

        @if( request()->user()->can('board.review') )
            <div class="mx-2">

                <x-secondary-button
                    wire:click="clearRecords"
                    wire:loading.attr="disabled"
                    class="transition transform duration-700 ease-in-out">
                <span
                    wire:loading.remove
                    wire:target="clearRecords">
                    Clear Records
                </span>
                    <span
                        wire:loading
                        wire:target="clearRecords">
                    Clearing...
                </span>
                </x-secondary-button>

            </div>
        @endif


    </div>

    {{ $this->table }}
</div>
