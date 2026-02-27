@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp
<div wire:poll.60s.visible="updateFaxData" class="w-full px-4">


    @if($datasource->ringcentral_client_id === null)
        <x-alert-info title="RingCentral API Not Configured" description="Please setup the RingCentral integration in System settings." />
    @elseif($state['ringcentral_failed_faxes'] === false)
        <x-alert-failure title="RingCentral API Error" description="Unable to load failed fax list from RingCentral API." />
    @elseif(count($state['ringcentral_failed_faxes']))
        <div class="shadow overflow-hidden border-b border-gray-300  sm:rounded-lg bg-gray-50   my-4">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 ">Ring Central</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 0">
                    View and re-send faxes from Ring Central
                </p>
            </div>
            <table class="min-w-full divide-y divide-gray-200  text-center">
                <thead class="">
                <tr class="sticky top-0">
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Fax Number
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Pages
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">
                        Status
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white  divide-y divide-gray-200 ">

                @foreach($state['ringcentral_failed_faxes'] as $row)
                    <tr class="group  transform transition duration-700 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">
                            @foreach($row['to'] as $recipient)
                                {{ $recipient['phoneNumber'] }}
                            @endforeach

                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">
                            <small>
                                {{ Carbon::parse($row['creationTime'], 'UTC')->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y g:i:s A T') }}
                            </small>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">
                            {{ $row['faxPageCount'] }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900   transform transition duration-700 ease-in-out">

                            @if($row['messageStatus'] === 'SendingFailed')
                                <span class="text-red-500">
                                    {{ ucwords($row['messageStatus']) }}
                                </span>
                            @elseif($row['messageStatus'] === 'Sent')
                                <span class="text-green-500">
                                    {{ ucwords($row['messageStatus']) }}
                                </span>
                            @else
                                {{ ucwords($row['messageStatus']) }}
                            @endif

                            @if($row['messageStatus'] === 'SendingFailed' || $row['messageStatus'] === 'Sent')
                                <a wire:click="openSendFaxDialog('{{ $row['id'] }}')" wire:loading.attr="disabled"
                                   title="Resend fax" class="hover:text-indigo-500 cursor-pointer">
                                    <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </a>
                            @endif

                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <!-- Delete User Confirmation Modal -->
        <x-dialog-modal wire:model.live="confirmResendFax">
            <x-slot name="title">
                {{ __('Resend Fax') }}
            </x-slot>

            <x-slot name="content">
                <span class="italic text-lg ">Are you sure you want to resend the fax?</span>
                <strong class="block text-gray-500 0">
                    Ring Central Fax ID <code class=" text-gray-700">{{ $faxIdToSend }}</code>
                </strong>

                <label class="mt-4 mb-0 block">Send fax to a different number:</label>
                <x-input class="my-2 py-2 px-2" id="faxInfo" wire:model.live="state.faxInfo.faxNumber" />

                <p class="text-xs  text-gray-500">
                    <strong>Careful!</strong> This must be an <a href="https://www.twilio.com/docs/glossary/what-e164" target="_blank" class="hover:underline">E.164</a> formatted telephone number!
                    <small class="block  text-gray-600">For U.S. and Canada, this will mean <code>+1</code> then your 10-digit telephone number. I.e., +15551234567</small>
                </p>

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmResendFax')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="resendFax()" wire:loading.attr="disabled">
                    {{ __('Resend Fax') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @else
        <x-alert-info title="RingCentral API Results" description="No fax message results were found." />
    @endif

    <div class="mb-0 mt-12">
        <h3 class="text-lg leading-6 font-medium text-gray-900 ">Fax Technical Details</h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500 0">
            This section is informational for troubleshooting the IS Fax and Ring Central integration within Mission Control.
        </p>
    </div>

    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="px-4 py-5 mr-2 bg-white   shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate ">To Send Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-yellow-700  text-center">
                {{ $state['files_to_send_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-white   shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate ">Sent Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-green-700  text-center">
                {{ $state['files_in_sent_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-white   shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate ">Failed Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-red-700  text-center">
                {{ $state['files_in_fail_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-white   shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate ">Pre-Proc Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-blue-700  text-center">
                {{ $state['files_in_pre_count'] }}
            </dd>
        </div>
    </dl>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg my-3  ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 ">To Send Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 0">Files in this directory are waiting for Mission Control to process and submit to Ring Central API.</p>
        </div>
        <div class="border-t border-gray-300  px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200 sm:">
                @foreach($state['files_to_send'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-gray-900 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
                            @if(Str::endsWith($file, '.cap'))
                                Fax Message
                            @elseif(Str::endsWith($file,'.fs' ))
                                Fax Metadata
                            @else
                                Unknown
                            @endif
                        </dd>
                    </div>
                @endforeach

            </dl>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg my-3  ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 ">Sent Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 0">Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.</p>
        </div>
        <div class="border-t border-gray-300  px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200 sm:">
                @foreach($state['files_in_sent'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-gray-900 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
                            @if(Str::endsWith($file, '.cap'))
                                Fax Message
                            @elseif(Str::endsWith($file,'.fs' ))
                                Fax Metadata
                            @else
                                Unknown
                            @endif
                        </dd>
                    </div>
                @endforeach

            </dl>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg my-3  ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 ">Fail Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 0">Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.</p>
        </div>
        <div class="border-t border-gray-300  px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200  sm:">
                @foreach($state['files_in_fail'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-gray-900 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
                            @if(Str::endsWith($file, '.cap'))
                                Fax Message
                            @elseif(Str::endsWith($file,'.fs' ))
                                Fax Metadata
                            @else
                                Unknown
                            @endif
                        </dd>
                    </div>
                @endforeach

            </dl>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg my-3  ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 ">Pre-Proc Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 0">Files in this directory are not supported at this time.</p>
        </div>
        <div class="border-t border-gray-300  px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200 sm:">
                @foreach($state['files_in_pre'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-gray-900 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
                            @if(Str::endsWith($file, '.cap'))
                                Fax Message
                            @elseif(Str::endsWith($file,'.fs' ))
                                Fax Metadata
                            @else
                                Unknown
                            @endif
                        </dd>
                    </div>
                @endforeach

            </dl>
        </div>
    </div>
</div>
