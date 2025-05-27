@props(['title' => '', 'description' => ''])
<div>
    <div class="rounded-md bg-red-50 border border-red-200 p-4 my-6 shadow">
        <div class="flex">
            <div class="shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-red-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                </svg>

            </div>
            <div class="ml-3">
                <h3 class="text-sm font-semibold text-red-800">{{ $title }}</h3>
                <div class="mt-2 text-sm text-red-700">
                    {{ $description }}
                </div>
            </div>
        </div>
    </div>

</div>
