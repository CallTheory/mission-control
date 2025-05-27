@props(['label' => '', 'details' => '', 'link' => ''])<div class="px-2 py-3 sm:grid sm:grid-cols-3 lg:grid-cols-2 sm:gap-4 sm:px-0">
    <dt class="text-sm font-semibold leading-6 text-gray-900">{{ $label }}</dt>
    <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2  lg:col-span-1 sm:mt-0">
        @if(strlen($link))
            <a href="{{ $link }}" class="font-semibold hover:underline text-indigo-600">{{ $details }}</a>
        @else
            {{ $details }}
        @endif
    </dd>
</div>
