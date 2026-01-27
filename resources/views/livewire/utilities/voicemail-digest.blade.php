<div class="w-full">

    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    <div class="block bg-white rounded border border-gray-300 shadow space-y-2 w-full my-4 py-4">
        <div class="px-4">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-base font-semibold leading-6 text-gray-900">Voicemail Digest Schedules</h1>
                    <p class="mt-2 text-sm text-gray-700">
                        Schedule automated emails with call recordings and transcriptions for your accounts.
                    </p>
                </div>
                <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                    <x-button wire:click="openCreateModal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Schedule
                    </x-button>
                </div>
            </div>
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle px-4">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Name</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Account</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Schedule</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Last Run</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Next Run</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Enabled</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse($schedules as $schedule)
                                <tr>
                                    <td class="whitespace-nowrap py-4 px-3 text-sm font-medium text-gray-900">
                                        <x-button wire:click="edit({{ $schedule->id }})" class="text-sm">
                                            {{ $schedule->name }}
                                        </x-button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        @if($schedule->client_number)
                                            Client: {{ $schedule->client_number }}
                                        @elseif($schedule->billing_code)
                                            Billing: {{ $schedule->billing_code }}
                                        @else
                                            <span class="text-gray-400">All allowed</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <span class="capitalize">{{ $schedule->schedule_type }}</span>
                                        @if($schedule->schedule_time)
                                            at {{ $schedule->schedule_time }}
                                        @endif
                                        @if($schedule->schedule_type === 'weekly')
                                            on {{ ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$schedule->schedule_day_of_week ?? 0] }}
                                        @elseif($schedule->schedule_type === 'monthly')
                                            on day {{ $schedule->schedule_day_of_month ?? 1 }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $schedule->last_run_at?->format('M j, g:i A') ?? 'Never' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $schedule->next_run_at?->format('M j, g:i A') ?? 'Not scheduled' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        <button
                                            wire:click="toggleEnabled({{ $schedule->id }})"
                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 {{ $schedule->enabled ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                            role="switch"
                                            aria-checked="{{ $schedule->enabled ? 'true' : 'false' }}"
                                        >
                                            <span
                                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $schedule->enabled ? 'translate-x-5' : 'translate-x-0' }}"
                                            ></span>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm space-x-2">
                                        <x-secondary-button wire:click="openSendNowModal({{ $schedule->id }})" class="text-xs">
                                            Send Now
                                        </x-secondary-button>
                                        <x-danger-button wire:confirm="Are you sure you want to delete this schedule?" wire:click="delete({{ $schedule->id }})">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </x-danger-button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-gray-500 text-sm p-4">No voicemail digest schedules found. Click "New Schedule" to create one.</td></tr>
                            @endforelse
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $schedules->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    @if($showCreateModal)
        <div class="absolute z-100">
            <x-dialog-modal wire:model.live="showCreateModal">
                <x-slot name="title">
                    <div class="flex text-2xl text-gray-900 font-bold">
                        Voicemail Digest &middot; New Schedule
                    </div>
                </x-slot>
                <x-slot name="content">
                    @include('livewire.utilities.voicemail-digest-form')
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="closeCreateModal" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="create" wire:loading.attr="disabled">
                        Create Schedule
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if($editingRecord)
        <div class="absolute z-100">
            <x-dialog-modal wire:model.live="editingRecord">
                <x-slot name="title">
                    <div class="flex text-2xl text-gray-900 font-bold">
                        Voicemail Digest &middot; Edit Schedule
                    </div>
                </x-slot>
                <x-slot name="content">
                    @include('livewire.utilities.voicemail-digest-form')
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="closeEditModal" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="update({{ $editingRecord }})" wire:loading.attr="disabled">
                        Save Changes
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif

    {{-- Send Now Modal --}}
    @if($showSendNowModal)
        <div class="absolute z-100">
            <x-dialog-modal wire:model.live="showSendNowModal">
                <x-slot name="title">
                    <div class="flex text-2xl text-gray-900 font-bold">
                        Send Voicemail Digest Now
                    </div>
                </x-slot>
                <x-slot name="content">
                    <p class="text-sm text-gray-600 mb-4">
                        Select the date range for recordings to include in this email.
                    </p>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="send_now_start_date" class="font-semibold" value="{{ __('Start Date/Time') }}" />
                        <x-input id="send_now_start_date" type="datetime-local" class="mt-1 block w-full" wire:model="sendNowState.start_date" />
                        <x-input-error for="sendNowState.start_date" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="send_now_end_date" class="font-semibold" value="{{ __('End Date/Time') }}" />
                        <x-input id="send_now_end_date" type="datetime-local" class="mt-1 block w-full" wire:model="sendNowState.end_date" />
                        <x-input-error for="sendNowState.end_date" class="mt-2" />
                    </div>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="closeSendNowModal" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="sendNow" wire:loading.attr="disabled">
                        Send Email
                    </x-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    @endif
</div>
