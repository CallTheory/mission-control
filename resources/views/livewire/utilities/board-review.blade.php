@php
    use App\Models\Stats\Helpers;
    $boardCheckCategories = Helpers::boardCheckCategories();

@endphp
<div class="w-full">
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>

    {{ $this->table }}
</div>
