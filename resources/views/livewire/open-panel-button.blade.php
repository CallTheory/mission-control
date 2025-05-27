<div>

    @if($r->name)
        <a class="group-hover:underline cursor-pointer text-indigo-400 hover:text-indigo-600"
           wire:click="$dispatch('openPanel', { r: {{$r->id}}})">
            {{ $r->name }}
        </a>
    @else
        <a class="group-hover:underline cursor-pointer text-indigo-400 hover:text-indigo-600"
           wire:click="$dispatch('openFreshPanel')">
            <svg class="w-5 h-5 inline align-middle" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z">
                </path>
            </svg>
        </a>
    @endif

</div>
