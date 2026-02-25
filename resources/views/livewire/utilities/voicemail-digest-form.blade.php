<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="name" class="font-semibold" value="{{ __('Schedule Name') }}" />
    <x-input id="name" type="text" class="mt-1 block w-full" wire:model="state.name" placeholder="e.g., Daily Recordings Report" />
    <small class="text-xs text-gray-400">A friendly name for this schedule</small>
    <x-input-error for="state.name" class="mt-2" />
</div>

<div class="grid grid-cols-2 gap-4">
    <div class="col-span-1 my-4">
        <x-label for="client_number" class="font-semibold" value="{{ __('Client Number') }}" />
        <x-input id="client_number" type="text" list="client_numbers_list" class="mt-1 block w-full" wire:model="state.client_number" placeholder="Type to search or select..." autocomplete="off" />
        <datalist id="client_numbers_list">
            <option value="">All Allowed Accounts</option>
            @foreach($clients as $client)
                <option value="{{ $client->ClientNumber }}">{{ $client->ClientNumber }} - {{ $client->ClientName }}</option>
            @endforeach
        </datalist>
        <small class="text-xs text-gray-400">Select a client or leave blank for all allowed accounts</small>
        <x-input-error for="state.client_number" class="mt-2" />
    </div>

    <div class="col-span-1 my-4">
        <x-label for="billing_code" class="font-semibold" value="{{ __('Billing Code') }}" />
        <x-input id="billing_code" type="text" class="mt-1 block w-full" wire:model="state.billing_code" placeholder="Optional" />
        <small class="text-xs text-gray-400">Filter by billing code</small>
        <x-input-error for="state.billing_code" class="mt-2" />
    </div>
</div>

<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="recipients" class="font-semibold" value="{{ __('Recipients') }}" />
    <textarea id="recipients" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.recipients" placeholder="email@example.com"></textarea>
    <small class="text-xs text-gray-400">One email address per line</small>
    <x-input-error for="state.recipients" class="mt-2" />
</div>

<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="subject" class="font-semibold" value="{{ __('Email Subject') }}" />
    <x-input id="subject" type="text" class="mt-1 block w-full" wire:model="state.subject" />
    <small class="text-xs text-gray-400">Subject line for the email</small>
    <x-input-error for="state.subject" class="mt-2" />
</div>

<div class="grid grid-cols-2 gap-4">
    <div class="col-span-1 my-4">
        <x-label for="schedule_type" class="font-semibold" value="{{ __('Schedule Type') }}" />
        <select id="schedule_type" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model.live="state.schedule_type">
            @foreach($scheduleTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error for="state.schedule_type" class="mt-2" />
    </div>

    @if($state['schedule_type'] !== 'immediate')
    <div class="col-span-1 my-4">
        <x-label for="schedule_time" class="font-semibold" value="{{ __('Time') }}" />
        <x-input id="schedule_time" type="time" class="mt-1 block w-full" wire:model="state.schedule_time" />
        <small class="text-xs text-gray-400">Time to send (for daily/weekly/monthly)</small>
        <x-input-error for="state.schedule_time" class="mt-2" />
    </div>
    @endif
</div>

@if($state['schedule_type'] === 'immediate')
<div class="col-span-6 sm:col-span-4 my-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
    <p class="text-sm text-blue-700">
        <strong>Immediate mode:</strong> The system checks for new recordings every minute. Each recording found will be sent as an individual email.
    </p>
</div>
@endif

@if($state['schedule_type'] === 'weekly')
<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="schedule_day_of_week" class="font-semibold" value="{{ __('Day of Week') }}" />
    <select id="schedule_day_of_week" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.schedule_day_of_week">
        @foreach($daysOfWeek as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <x-input-error for="state.schedule_day_of_week" class="mt-2" />
</div>
@endif

@if($state['schedule_type'] === 'monthly')
<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="schedule_day_of_month" class="font-semibold" value="{{ __('Day of Month') }}" />
    <select id="schedule_day_of_month" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.schedule_day_of_month">
        @for($i = 1; $i <= 31; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
        @endfor
    </select>
    <small class="text-xs text-gray-400">For months with fewer days, will run on the last day</small>
    <x-input-error for="state.schedule_day_of_month" class="mt-2" />
</div>
@endif

<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="timezone" class="font-semibold" value="{{ __('Timezone') }}" />
    <select id="timezone" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.timezone">
        @foreach($timezones as $tz)
            <option value="{{ $tz }}">{{ $tz }}</option>
        @endforeach
    </select>
    <x-input-error for="state.timezone" class="mt-2" />
</div>

<div class="grid grid-cols-2 gap-4">
    <div class="col-span-1 my-4">
        <x-label for="include_transcription" class="font-semibold" value="{{ __('Include Transcription') }}" />
        <select id="include_transcription" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.include_transcription">
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>
        <small class="text-xs text-gray-400">Include transcription in email body</small>
        <x-input-error for="state.include_transcription" class="mt-2" />
    </div>

    <div class="col-span-1 my-4">
        <x-label for="include_call_metadata" class="font-semibold" value="{{ __('Include Call Metadata') }}" />
        <select id="include_call_metadata" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.include_call_metadata">
            <option value="1">Yes</option>
            <option value="0">No</option>
        </select>
        <small class="text-xs text-gray-400">Include caller, agent, time info</small>
        <x-input-error for="state.include_call_metadata" class="mt-2" />
    </div>
</div>
