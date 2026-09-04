@if ($errors->any())
    <div {{ $attributes }}>
        <div class="font-medium text-danger">{{ __('Whoops! Something went wrong.') }}</div>

        <ul class="mt-3 list-inside text-sm text-danger-soft-fg bg-danger-soft py-2 px-2 rounded border border-danger">
            @foreach ($errors->all() as $error)
                <li class="font-semibold">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
