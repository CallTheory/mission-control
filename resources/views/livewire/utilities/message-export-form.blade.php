<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="name" class="font-semibold" value="{{ __('Export Name') }}" />
    <x-input id="name" type="text" class="mt-1 block w-full" wire:model="state.name" placeholder="e.g., Daily Client Messages" />
    <small class="text-xs text-gray-400">A friendly name for this export</small>
    <x-input-error for="state.name" class="mt-2" />
</div>

<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="client_number" class="font-semibold" value="{{ __('Account (Client Number)') }}" />
    <select id="client_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model.live="state.client_number">
        <option value="">-- Select an Account --</option>
        @foreach($clients as $client)
            <option value="{{ $client->ClientNumber }}">{{ $client->ClientNumber }} - {{ $client->ClientName }}</option>
        @endforeach
    </select>
    <small class="text-xs text-gray-400">Select the account to export messages for</small>
    <x-input-error for="state.client_number" class="mt-2" />
</div>

{{-- Field Selection --}}
<div class="col-span-6 sm:col-span-4 my-4">
    <x-label class="font-semibold" value="{{ __('Message Fields to Include') }}" />

    @if($loadingFields)
        <div class="mt-2 p-4 border border-gray-200 rounded-md bg-gray-50">
            <div class="flex items-center text-sm text-gray-500">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Discovering available fields for this account...
            </div>
        </div>
    @elseif(empty($state['client_number']))
        <div class="mt-2 p-4 border border-gray-200 rounded-md bg-gray-50">
            <p class="text-sm text-gray-500">Select an account above to discover available message fields.</p>
        </div>
    @elseif(empty($availableFields))
        <div class="mt-2 p-4 border border-yellow-200 rounded-md bg-yellow-50">
            <p class="text-sm text-yellow-700">No message fields found for this account. The account may not have any recent messages with field data.</p>
        </div>
    @else
        <div class="mt-2 flex gap-2 mb-2">
            <x-secondary-button wire:click="selectAllFields" class="text-xs">Select All</x-secondary-button>
            <x-secondary-button wire:click="deselectAllFields" class="text-xs">Deselect All</x-secondary-button>
            <span class="text-xs text-gray-500 self-center ml-2">{{ count($state['selected_fields'] ?? []) }} of {{ count($availableFields) }} selected</span>
        </div>
        <div class="mt-1 p-3 border border-gray-200 rounded-md bg-gray-50 max-h-60 overflow-y-auto">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($availableFields as $field)
                    <label class="flex items-center text-sm cursor-pointer hover:bg-gray-100 rounded px-1 py-0.5">
                        <input type="checkbox" value="{{ $field }}" wire:model="state.selected_fields" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-2" />
                        <span class="truncate" title="{{ $field }}">{{ $field }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <x-input-error for="state.selected_fields" class="mt-2" />
    @endif
</div>

{{-- Filter --}}
@if(!empty($availableFields))
<div class="col-span-6 sm:col-span-4 my-4 p-3 border border-gray-200 rounded-md bg-gray-50">
    <x-label class="font-semibold" value="{{ __('Filter (Optional)') }}" />
    <small class="text-xs text-gray-400 block mb-2">Only include messages where a specific field matches a value</small>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-label for="filter_field" value="{{ __('Field') }}" />
            <select id="filter_field" class="mt-1 block w-full border border-gray-300 rounded-md shadow text-sm" wire:model="state.filter_field">
                <option value="">-- No Filter --</option>
                @foreach($availableFields as $field)
                    <option value="{{ $field }}">{{ $field }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-label for="filter_value" value="{{ __('Value') }}" />
            <x-input id="filter_value" type="text" class="mt-1 block w-full text-sm" wire:model="state.filter_value" placeholder="Expected value" />
        </div>
    </div>
</div>
@endif

{{-- Include Call Info --}}
<div class="col-span-6 sm:col-span-4 my-4">
    <x-label for="include_call_info" class="font-semibold" value="{{ __('Include Call Information') }}" />
    <select id="include_call_info" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model="state.include_call_info">
        <option value="1">Yes</option>
        <option value="0">No</option>
    </select>
    <small class="text-xs text-gray-400">Include Call ID, Client Name/Number, Agent, and Timestamp columns</small>
</div>

{{-- Schedule --}}
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

    @if(!in_array($state['schedule_type'], ['manual', 'immediate']))
    <div class="col-span-1 my-4">
        <x-label for="schedule_time" class="font-semibold" value="{{ __('Time') }}" />
        <x-input id="schedule_time" type="time" class="mt-1 block w-full" wire:model="state.schedule_time" />
        <small class="text-xs text-gray-400">Time to run (for daily/weekly/monthly)</small>
        <x-input-error for="state.schedule_time" class="mt-2" />
    </div>
    @endif
</div>

@if($state['schedule_type'] === 'manual')
<div class="col-span-6 sm:col-span-4 my-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
    <p class="text-sm text-blue-700">
        <strong>Manual mode:</strong> This export will only run when you click "Run Now". No scheduled emails will be sent.
    </p>
</div>
@endif

@if($state['schedule_type'] === 'immediate')
<div class="col-span-6 sm:col-span-4 my-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
    <p class="text-sm text-blue-700">
        <strong>Immediate mode:</strong> The system checks for new messages every minute and exports any found since the last run.
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

{{-- Recipients (only for scheduled types) --}}
@if($state['schedule_type'] !== 'manual')
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
@endif
