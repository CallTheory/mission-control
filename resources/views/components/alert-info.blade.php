@props(['title' => '', 'description' => ''])
<div class="rounded-md bg-blue-50 border border-blue-200 p-4 my-6 shadow">
    <div class="flex">
        <div class="shrink-0">
            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-semibold text-blue-800">{{ $title }}</h3>
            <div class="mt-2 text-sm text-blue-700">
                {!! $description !!}
            </div>
        </div>
    </div>
</div>
