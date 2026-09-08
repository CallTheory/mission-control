@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp
<div wire:poll.5000ms.visible="updateFaxData" class="w-full px-4">

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

    <div class="bg-surface shadow overflow-hidden sm:rounded-lg my-3 ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-surface-fg ">To Send Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-muted">Files in this directory are waiting for Mission Control to process and submit to mFax API.</p>
        </div>
        <div class="border-t border-border px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-border-soft sm:">
                @foreach($state['files_to_send'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-muted ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-surface-fg 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
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

    <div class="bg-surface shadow overflow-hidden sm:rounded-lg my-3 ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-surface-fg ">Sent Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-muted">Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.</p>
        </div>
        <div class="border-t border-border px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-border-soft sm:">
                @foreach($state['files_in_sent'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-muted ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-surface-fg 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
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

    <div class="bg-surface shadow overflow-hidden sm:rounded-lg my-3 ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-surface-fg ">Fail Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-muted">Files in this directory are waiting for Amtelco's Intelligent Series Fax Service to process.</p>
        </div>
        <div class="border-t border-border px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-border-soft sm:">
                @foreach($state['files_in_fail'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-muted ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-surface-fg 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
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

    <div class="bg-surface shadow overflow-hidden sm:rounded-lg my-3 ">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-surface-fg ">Pre-Proc Fax Folder</h3>
            <p class="mt-1 max-w-2xl text-sm text-muted">Files in this directory are not supported at this time.</p>
        </div>
        <div class="border-t border-border px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-border-soft sm:">
                @foreach($state['files_in_pre'] as $file)
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-muted ">{{ $file }}</dt>
                        <dd class="mt-1 text-xs text-surface-fg 0 sm:mt-0 sm:col-span-2 float-right w-fullt">
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
    <x-filament-actions::modals />
</div>
