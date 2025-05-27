@if ($errors->any())
    <div {{ $attributes }}>
        <div class="font-medium text-white">{{ __('Whoops! Something went wrong.') }}</div>

        <ul class="mt-3 list-inside text-sm text-red-700 bg-red-100 py-2 px-2 rounded border border-red-300">
            @foreach ($errors->all() as $error)
                <li class="font-semibold">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
