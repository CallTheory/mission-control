<div class="w-full">

    <div class="block bg-white rounded border border-gray-300 shadow space-y-2 w-full my-4 py-4">
        <div class="px-4">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-base font-semibold leading-6 text-gray-900">Better Email Setup</h1>
                    <p class="mt-2 text-sm text-gray-700">
                        Manage the better email configurations for individual Better Email configurations.
                    </p>
                </div>
            </div>
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle px-4">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client Number</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email Subject</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Recipients</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Drop Location</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @forelse($logs as $log)
                                <tr>
                                    <td class="whitespace-nowrap py-4 px-3 text-sm font-medium text-gray-900">
                                        <x-button wire:click="editBetterEmail({{$log->id}})" class="text-sm">
                                            {{ $log->client_number }}
                                        </x-button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{  $log->subject }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-gray-500">
                                       @foreach(json_decode($log->recipients) as $recipient)
                                            {{ $recipient }}<br>
                                       @endforeach
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500">
                                        <code class="bg-gray-100 px-2 py-1 rounded shadow">
                                            {{ config('app.unc_path') }}\better-emails\{{ $log->client_number }}\{{ $log->id }}
                                        </code>
                                    </td>
                                    <td>
                                        <x-danger-button wire:confirm="Are you sure you want to delete this email configuration?" wire:click="deleteBetterEmail({{ $log->id }})">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>

                                        </x-danger-button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-gray-500 text-sm p-4">No Better Email configurations found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($editingRecord)
        <div class="absolute z-100">
            <x-dialog-modal wire:model.live="editingRecord">
                <x-slot name="title">
                    <div class="flex text-2xl text-gray-900 font-bold">
                        Better Email &middot; Edit
                    </div>
                </x-slot>
                <x-slot name="content">

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="client_number" class="font-semibold" value="{{ __('Client Number') }}" />
                        <x-input id="client_number" type="text" class="mt-1 block w-full " wire:model.defer="state.client_number" />
                        <small class="text-xs text-gray-400">The Intelligent Series Client Number</small>
                        <x-input-error for="state.client_number" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="subject" class="font-semibold" value="{{ __('Subject') }}" />
                        <x-input id="subject" type="text" class="mt-1 block w-full " wire:model.defer="state.subject" />
                        <small class="text-xs text-gray-400">The subject line for the email</small>
                        <x-input-error for="state.subject" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="title" class="font-semibold" value="{{ __('Title') }}" />
                        <x-input id="title" type="text" class="mt-1 block w-full " wire:model.defer="state.title" />
                        <small class="text-xs text-gray-400">The title of the email body</small>
                        <x-input-error for="state.title" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="description" class="font-semibold" value="{{ __('Description') }}" />
                        <x-input id="description" type="text" class="mt-1 block w-full " wire:model.defer="state.description" />
                        <small class="text-xs text-gray-400">The description is displayed under the title and in the email preview</small>
                        <x-input-error for="state.description" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="recipients" class="font-semibold" value="{{ __('Recipients') }}" />
                        <textarea id="recipients" class="mt-1 block w-full border border-gray-300 rounded-md shadow" wire:model.defer="state.recipients"></textarea>
                        <small class="text-xs text-gray-400">One email address per line</small>
                        <x-input-error for="state.recipients" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="report_metadata" class="font-semibold" value="{{ __('Include Report Metadata') }}" />
                        <select id="report_metadata" name="report_metadata" class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.report_metadata">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <small class="text-xs text-gray-400">Includes a table of metadata about the report</small>
                        <x-input-error for="state.report_metadata" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="message_history" class="font-semibold" value="{{ __('Include Message History') }}" />
                        <select id="message_history" name="message_history" class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.message_history">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <small class="text-xs text-gray-400">This can also be controlled via Amtelco SendMessages element and Schedule SendMessages</small>
                        <x-input-error for="state.message_history" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="theme" class="font-semibold" value="{{ __('Theme') }}" />
                        <select id="theme" name="theme"  class="mt-1 block w-full border border-gray-300 rounded-md shadow"
                                wire:model.defer="state.theme">
                            <option value="standard">Standard</option>
                            <option value="dark">Dark</option>
                        </select>
                        <small class="text-xs text-gray-400">Select one of our standard templates or contact us for a custom-themed email template</small>
                        <x-input-error for="state.theme" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo" class="font-semibold" value="{{ __('Logo Url') }}" />
                        <x-input id="logo" type="text" class="mt-1 block w-full " wire:model.defer="state.logo" />
                        <small class="text-xs text-gray-400">The logo to displayed at the top of the email</small>
                        <x-input-error for="state.logo" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo_alt" class="font-semibold" value="{{ __('Logo Alt Text') }}" />
                        <x-input id="logo_alt" type="text" class="mt-1 block w-full " wire:model.defer="state.logo_alt" />
                        <small class="text-xs text-gray-400">The alt text for the logo</small>
                        <x-input-error for="state.logo_alt" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="logo_link" class="font-semibold" value="{{ __('Logo Link') }}" />
                        <x-input id="logo_link" type="text" class="mt-1 block w-full " wire:model.defer="state.logo_link" />
                        <small class="text-xs text-gray-400">The link to visit if they click the logo</small>
                        <x-input-error for="state.logo_link" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="button_text" class="font-semibold" value="{{ __('Button Text') }}" />
                        <x-input id="button_text" type="text" class="mt-1 block w-full " wire:model.defer="state.button_text" />
                        <small class="text-xs text-gray-400">The text for the button at the end of the email</small>
                        <x-input-error for="state.button_text" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4 my-4">
                        <x-label for="button_link" class="font-semibold" value="{{ __('Button Link') }}" />
                        <x-input id="button_link" type="text" class="mt-1 block w-full " wire:model.defer="state.button_link" />
                        <small class="text-xs text-gray-400">The link (both https:// and mailto:// are supported)</small>
                        <x-input-error for="state.button_link" class="mt-2" />
                    </div>

                </x-slot>

                <x-slot name="footer">

                    <x-secondary-button wire:click="closeEditModal" wire:loading.attr="disabled">
                        Cancel
                    </x-secondary-button>

                    <x-button class="ml-2" wire:click="updateBetterEmail({{$editingRecord}})" wire:loading.attr="disabled">
                        Save
                    </x-button>

                </x-slot>
            </x-dialog-modal>
        </div>
    @endif
</div>
