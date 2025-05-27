@php
$sortIconAsc= '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"class="h-3 ml-1 text-indigo-700 my-1 align-text-bottom">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3" />
</svg>';
$sortIconDesc = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3  ml-1 text-indigo-700  my-1 align-text-bottom">
  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75 12 3m0 0 3.75 3.75M12 3v18" />
</svg>';
@endphp
<div class="w-full inline-block">
    <div class="p-4 bg-gray-50 rounded shadow mb-2">
        <div class="inline-flex flex flex-wrap">
            <div class="mr-4 pr-4 my-2">
                <x-label for="client_number">Client Number</x-label>
                <x-input id="client_number" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="client_number" />
                <x-input-error for="client_number" class="mt-2" />
            </div>
            <div class="mr-4 pr-4 my-2">
                <x-label for="billing_code">Billing Code</x-label>
                <x-input id="billing_code" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="billing_code" />
                <x-input-error for="billing_code" class="mt-2" />
            </div>
            <div class="mr-4 pr-4 my-2">
                <x-label for="client_name">Client Name</x-label>
                <x-input id="client_name" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="client_name" />
                <x-input-error for="client_name" class="mt-2" />
            </div>
            <div class="mr-4 pr-4 my-2">
                <x-label for="client_source">Source</x-label>
                <x-input id="client_source" wire:loading.attr="disabled" type="text" class="mt-1 " wire:model.defer="client_source" />
                <x-input-error for="client_source" class="mt-2" />
            </div>
            <div class="flex my-2">
                <div class="mr-2 pr-2">
                    <x-label for="account_setting">Account Setting</x-label>
                    <select id="account_setting" wire:loading.attr="disabled" class="mt-1 border border-gray-300 shadow rounded" wire:model.defer="account_setting">
                        <option value=""></option>
                        <option value="SaveDiscardedMessages">Save Discarded Messages</option>
                        <option value="CheckinPending">Checkin Pending</option>
                        <option value="LogVoice">Log Voice</option>
                        <option value="PerfectAnswer">Perfect Answer</option>
                        <option value="AutoConnect">Auto-Connect (Deprecated?)</option>
                        <option value="Emergency">Emergency</option>
                        <option value="DoneKeyCancelsScript">Done Key Cancels Script</option>
                        <option value="HangupRemovesWorkArea">Hangup Removes Work Area</option>
                        <option value="TransferConfRemovesWorkArea">Transfer Conference Removes Work Area</option>
                        <option value="PciCompliance">PCI Compliance</option>
                        <option value="OverrideOpLimit">Override Op Limit</option>
                        <option value="AnnounceATTA">Announce ATTA</option>
                        <option value="RepeatATTA">Repeat ATTA</option>
                        <option value="AnnounceCallsInQue">Announce Calls In Queue</option>
                        <option value="DontStartScriptOnFetch">Dont Start Script On Fetch</option>
                        <option value="ScreenCapture">Screen Capture</option>
                        <option value="SelectNextUndelMsgWhenDel">Select Next Undelivered Msg When Deleted</option>
                        <option value="PlayQualityPrompt">Play Quality Prompt</option>
                        <option value="LoggerBeep">Logger Beep</option>
                        <option value="SpecialOldToNew">Special Old To New</option>
                        <option value="SaveEditedSpecial">Save Edited Special</option>
                        <option value="ShowSpecials">Show Specials</option>
                        <option value="ShowInfos">Show Infos</option>
                        <option value="DirectCheckin">Direct Checkin</option>
                        <option value="VoiceMailPlayBeep">Voice Mail Play Beep</option>
                        <option value="VoiceMailOldToNew">Voice Mail Old To New</option>
                        <option value="VoiceMailRevert">Voice Mail Revert</option>
                        <option value="VmChgPasscode">Vm Change Passcode</option>
                        <option value="VmChgGreeting">Vm Change Greeting</option>
                        <option value="VoiceMailPrivate">Voice Mail Private</option>
                        <option value="VoiceMailANI">Voice Mail ANI</option>
                        <option value="SecureVMTransfer">Secure VM Transfer</option>
                        <option value="NewVMRunsMergecomm">New VM Runs Mergecomm</option>
                        <option value="ExcludeFromSurvey">Exclude From Survey</option>
                        <option value="LogWhenMessageViewed">Log When Message Viewed</option>
                        <option value="UseOrgClientForDIDLimit">Use Original Client For DID Limit</option>
                        <option value="ExemptFromSystemHoliday">Exempt From System Holiday</option>
                        <option value="RecordPatch">Record Patch</option>
                        <option value="NoLoggerDialout">No Logger Dialout</option>
                        <option value="UseCallersCallerIdOnDialouts">Use Callers CallerId On Dialouts</option>
                        <option value="Voci">Voci</option>
                        <option value="Inactive">Inactive</option>
                        <option value="PresentAbandon">Present Abandon</option>
                        <option value="ExemptFromSystemEmergency">Exempt From System Emergency</option>
                    </select>
                    <x-input-error for="account_setting" class="mt-2" />
                </div>

                <div class="mr-4 pr-4">
                    <x-label for="account_setting_value">Setting Value</x-label>
                    <select id="account_setting_value" wire:loading.attr="disabled" class="mt-1 border border-gray-300 shadow rounded" wire:model.defer="account_setting_value">
                        <option value=""></option>
                        <option value="0">Off</option>
                        <option value="1">On</option>
                    </select>
                    <x-input-error for="account_setting_value" class="mt-2" />
                </div>
            </div>

            <div class="mr-4 pr-4 my-2">
                <x-label for="search_button">Filter</x-label>
                <x-button id="search_button" wire:loading.attr="disabled" class="mt-2" wire:click="applyFilter">
                    Apply Filter
                </x-button>
                <x-secondary-button id="reset_filter" wire:loading.attr="disabled" class="mt-2" wire:click="resetFilter">
                    Reset
                </x-secondary-button>

                <x-action-message class="ml-2 inline" on="saved">
                    <span class="text-green-500">&checkmark;</span>
                </x-action-message>
            </div>
        </div>
    </div>

    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">

                @if($clients)

                    <div class="my-2">
                        {{ $clients->links() }}
                    </div>
                    <div class="shadow overflow-hidden border border-gray-300  sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200  text-left">
                            <thead class="bg-gray-50">
                            <tr class="sticky top-0">
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    cltId
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    <a class="cursor-pointer flex whitespace-nowrap" wire:click="orderBy('ClientNumber')">
                                        Client Number @if($order_by === 'ClientNumber')  @if($order_direction === 'asc') {!! $sortIconAsc !!} @else {!! $sortIconDesc !!} @endif @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    <a class="cursor-pointer flex whitespace-nowrap" wire:click="orderBy('BillingCode')">
                                        Billing Code @if($order_by === 'BillingCode')  @if($order_direction === 'asc') {!! $sortIconAsc !!} @else {!! $sortIconDesc !!} @endif @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    <a class="cursor-pointer flex whitespace-nowrap" wire:click="orderBy('ClientName')">
                                        Client Name @if($order_by === 'ClientName')  @if($order_direction === 'asc') {!! $sortIconAsc !!} @else {!! $sortIconDesc !!} @endif @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    Directory Subject
                                </th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 tracking-wider whitespace-nowrap">
                                    Sources
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white  divide-y divide-gray-200 ">

                            @foreach($clients as $client)
                                <tr class="group  transform transition duration-700 ease-in-out">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400   transform transition duration-700 ease-in-out">
                                        {{ $client->cltId }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out font-semibold"
                                           href="/accounts/{{ $client->ClientNumber }}">
                                            {{ $client->ClientNumber }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                        {{ $client->BillingCode }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                        <a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out font-semibold"
                                           href="/accounts/{{ $client->ClientNumber }}">
                                            {{ $client->ClientName }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                        {{ $client->Directory ?? '' }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-900 transform transition duration-700 ease-in-out">
                                        @foreach($sources as $source)
                                            @if($source->cltId === $client->cltId)
                                               <x-client-source source="{{ $source->Source }}" />
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="my-2">
                        {{ $clients->links() }}
                    </div>
                @else
                    No records found.
                @endif

            </div>
        </div>
    </div>
</div>
