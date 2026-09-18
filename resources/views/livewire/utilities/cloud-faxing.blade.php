@php
    use Carbon\Carbon;
@endphp
<div wire:poll.30s.visible="updateFaxData" class="w-full px-4">

    @if($datasource->mfax_api_key === null)
        <x-alert-info title="mFax API Not Configured" description="Please setup the mFax integration in System settings." />
    @elseif($state['mfax_failed_faxes'] === false)
        <x-alert-failure title="mFax API Error" description="Unable to load failed fax list from mFax API." />
    @elseif(count($state['mfax_failed_faxes']))
        <div class="shadow overflow-hidden border-b border-border sm:rounded-lg bg-surface-2 my-4">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-surface-fg ">Documo mFax</h3>
                <p class="mt-1 max-w-2xl text-sm text-muted">
                    View and re-send faxes from mFax
                </p>
            </div>
            <table class="min-w-full divide-y divide-border-soft text-center">
                <thead class="">
                <tr class="sticky top-0">
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Client Number
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Fax Number
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Pages
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Attempts
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-xs font-medium text-muted 0 uppercase tracking-wider whitespace-nowrap">
                        Info
                    </th>
                </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-border-soft ">

                @foreach($state['mfax_failed_faxes'] as $row)
                    <tr class="group  transform transition duration-700 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-surface-fg transform transition duration-700 ease-in-out">

                            @foreach($row['tags'] as $key => $t)
                                @if(isset($tags[$row['tags'][$key]['uuid']]) && strtolower($t['name']) !== 'resent')
                                    {{ $tags[$row['tags'][$key]['uuid']] }}
                                    @break
                                @else
                                @endif
                            @endforeach

                        </td>

                        <td class="px-6 py-4 text-xs text-surface-fg transform transition duration-700 ease-in-out">
                            {{ $row['faxNumber'] }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-surface-fg transform transition duration-700 ease-in-out">
                            <small>
                                {{ Carbon::parse($row['createdAt'], 'UTC')->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y g:i:s A T') }}
                            </small>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-surface-fg transform transition duration-700 ease-in-out">
                            {{ $row['pagesComplete'] }} / {{ $row['pagesCount'] }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-surface-fg transform transition duration-700 ease-in-out">
                            {{ $row['faxAttempt'] ?? '???' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-surface-fg transform transition duration-700 ease-in-out">

                            @foreach($row['tags'] as $key => $t)
                                @if(isset($tags[$t['uuid']]) && strtolower($t['name']) === 'resent')

                                    <span class="text-danger">{{ $tags[$t['uuid']] ?? 'Unknown' }}</span> &middot;

                                    @break
                                @else
                                @endif
                            @endforeach


                            @if($row['status'] === 'failed')
                                <span class="text-danger">
                                    {{ ucwords($row['status']) }}
                                </span>
                            @elseif($row['status'] === 'transmitting')
                                <span class="text-warning">
                                    {{ ucwords($row['status']) }}
                                </span>
                            @elseif($row['status'] === 'success')
                                <span class="text-success">
                                    {{ ucwords($row['status']) }}
                                </span>
                            @else
                                {{ ucwords($row['status']) }}
                            @endif

                            @if($row['status'] === 'failed' || $row['status'] === 'success')
                                <a wire:click="mountAction('resendFax', { messageId: '{{ $row['messageId'] }}' })" wire:loading.attr="disabled"
                                   title="Resend fax" class="hover:text-primary cursor-pointer">
                                    <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </a>
                            @endif

                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-xs text-primary transform transition duration-700 ease-in-out">
                            {{ $row['resultInfo'] }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <!-- Delete User Confirmation Modal -->

    @else
        <x-alert-info title="mFax API Results" description="No fax message results were found." />
    @endif

    <div class="mb-0 mt-12">
        <h3 class="text-lg leading-6 font-medium text-surface-fg ">Fax Technical Details</h3>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            This section is informational for troubleshooting the IS Fax and mFax integration within Mission Control.
        </p>
        {{-- Whether delivery confirmations are arriving by webhook, or whether the fallback
             poller is carrying the load and spending mFax API quota to do it. --}}
        <p class="mt-1 text-xs text-muted">
            @if(!empty($state['webhook_last_received_at']))
                Delivery webhook last received
                {{ Carbon::parse($state['webhook_last_received_at'])->timezone(Auth::user()->timezone ?? 'UTC')->format('m/d/Y g:i:s A T') }}
            @else
                <span class="text-warning">No delivery webhook has ever been received</span> &mdash;
                delivery status is being polled instead.
            @endif
        </p>
    </div>

    <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="px-4 py-5 mr-2 bg-surface shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-muted truncate ">To Send Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-primary text-center">
                {{ $state['files_to_send_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-surface shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-muted truncate ">Sent Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-success text-center">
                {{ $state['files_in_sent_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-surface shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-muted truncate ">Failed Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-danger text-center">
                {{ $state['files_in_fail_count'] }}
            </dd>
        </div>

        <div class="px-4 py-5 mr-2 bg-surface shadow rounded-lg overflow-hidden sm:p-6">
            <dt class="text-sm font-medium text-muted truncate ">Pre-Proc Fax Folder</dt>
            <dd class="mt-1 text-4xl font-semibold text-info text-center">
                {{ $state['files_in_pre_count'] }}
            </dd>
        </div>
    </dl>

    @include('utilities.cloud-faxing.spool-folder', [
        'title' => 'To Send Folder',
        'description' => 'Files in this directory are waiting for Mission Control to process and submit to the mFax API.',
        'folder' => 'tosend',
        'files' => $state['files_to_send'],
        'canManage' => $this->canManageFaxSpool(),
    ])

    @include('utilities.cloud-faxing.spool-folder', [
        'title' => 'Sent Fax Folder',
        'description' => "Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.",
        'folder' => 'sent',
        'files' => $state['files_in_sent'],
        'canManage' => $this->canManageFaxSpool(),
    ])

    @include('utilities.cloud-faxing.spool-folder', [
        'title' => 'Fail Fax Folder',
        'description' => "Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.",
        'folder' => 'fail',
        'files' => $state['files_in_fail'],
        'canManage' => $this->canManageFaxSpool(),
    ])

    @include('utilities.cloud-faxing.spool-folder', [
        'title' => 'Pre-Proc Fax Folder',
        'description' => 'Files in this directory are not supported at this time.',
        'folder' => 'preproc',
        'files' => $state['files_in_pre'],
        'canManage' => $this->canManageFaxSpool(),
    ])

    <x-filament-actions::modals />
</div>
