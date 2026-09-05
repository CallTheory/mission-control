<div>
    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-success-soft p-4">
            <div class="flex">
                <div class="text-sm text-success">
                    {{ session('message') }}
                </div>
            </div>
        </div>
    @endif

    {{ $this->table }}
</div>
