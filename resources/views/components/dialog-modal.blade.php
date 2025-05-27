@props(['id' => null, 'maxWidth' => null])

<x-jetmodal :id="$id" :maxWidth="$maxWidth" {{ $attributes }} class="relative z-100">
    <div class="px-6 py-4">
        <div class="text-lg ">
            {{ $title }}
        </div>

        <div class="mt-4 ">
            {{ $content }}
        </div>
    </div>

    <div class="px-6 py-4 bg-gray-100   text-right">
        {{ $footer }}
    </div>
</x-jetmodal>
