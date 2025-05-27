<div>
    <a href="#" class="group-hover:underline cursor-pointer text-blue-600"  wire:click.prevent="$dispatch('openEmailPanel', { email: {{$email->id}}})">

        {{ $email->subject }}

    </a>
</div>
