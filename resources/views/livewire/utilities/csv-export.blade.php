<div class="w-full">
    <x-form-section submit="">
        <x-slot name="title">{{ __('Export Call Log to CSV') }}</x-slot>

        <x-slot name="description">
            Narrow the call log with the same filters the Analytics screen uses, preview
            how many calls match, then download them as a CSV.
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6">
                {{ $this->form }}
            </div>
        </x-slot>

        <x-slot name="actions">
            @if($queried)
                <span class="mr-3 text-sm text-muted">
                    {{ number_format($result_count) }} {{ Str::plural('call', $result_count) }} match
                </span>
            @endif

            @if($error_message)
                <span class="mr-3 text-sm text-danger">{{ $error_message }}</span>
            @endif

            <span class="mr-3">{{ $this->previewAction }}</span>
            {{ $this->exportAction }}
        </x-slot>
    </x-form-section>

    <x-filament-actions::modals />
</div>
