@php
    use App\Models\Stats\Helpers;
    $boardCheckCategories = Helpers::boardCheckCategories();

@endphp
<div class="w-full">
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>

    @if( request()->user()->can('board.report') )
        <div class="inline my-2">
            <form wire:target="exportPeopleSoft" wire:submit="exportPeopleSoft" class="mx-4 my-4">
                <button class="cursor-pointer px-3 py-2 bg-surface-inverse hover:bg-surface-inverse-hover text-surface-inverse-fg shadow rounded-lg transition transform duration-700 ease-in-out" type="submit">
                    <div class="" wire:loading>Exporting...</div>
                    <div wire:loading.remove>Export To PeoplePraise</div>
                </button>
            </form>
        </div>

    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
