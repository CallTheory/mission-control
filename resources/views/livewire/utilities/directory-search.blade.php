@php
use Illuminate\Support\Str;
@endphp
<div>
    <div class="flex space-x-4">
        <div class="mt-6 w-full">
            <form wire:submit="searchDirectoryContacts" class="bg-surface border border-border shadow rounded">
                <div class="px-4 py-5 sm:p-6">
                    <div class="block w-100">
                        <x-input value="{{ $searchQuery ?? '' }}" required name="searchQuery"
                                 id="searchQuery" type="text" class="mt-1 block w-full " wire:model="searchQuery" />
                        <span class="text-xs text-muted">Match the format (full or partial) that your directory uses!<br>(i.e., 614555 or 6145551234 or 614-555-1234)</span>
                        <x-input-error for="searchQuery" class="mt-2" />
                    </div>
                    <div class="block w-100 mt-4">
                        <select required id="contactSearchType"  class="mt-1 block w-full rounded border-border shadow" wire:model="contactSearchType">
                            <option value="phone">Phone</option>
                            <option value="email">Email</option>
                            <option value="fax">Fax</option>
                            <option value="wctp">WCTP</option>
                            <option value="msm">Secure Messaging</option>
                            <option value="sms">SMS</option>
                            <option value="cisco">Cisco</option>
                            <option value="tap">TAP Pager</option>
                            <option value="vocera">Vocera</option>
                        </select>
                        <span class="text-xs text-muted">The Intelligent Series Contact Method Type</span>
                        <x-input-error for="contactSearchType" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end px-4 py-3 bg-surface-2 text-right sm:px-6 shadow sm:rounded-bl-md sm:rounded-br-md">

                <span class="mr-3 text-sm" wire:loading>
                    {{ __('Searching...') }}
                </span>

                    <x-action-message class="mr-3 " on="search">
                        {{ __('Search complete.') }}
                    </x-action-message>

                    <x-button class="print:hidden">
                        {{ __('Search') }}
                    </x-button>
                </div>
            </form>
        </div>
        <div class="w-full">
            <x-alert-info title="Contact Method Search"
                          description="Search for partial or full matches against these Contact Methods:<br><br>
                      <strong>Phone</strong> &rarr; PhoneNumber<br>
                      <strong>Email</strong> &rarr; ToAddress<br>
                      <strong>Cisco</strong> &rarr; Device<br>
                      <strong>Fax</strong> &rarr; Phone<br>
                      <strong>WCTP</strong> &rarr; PagerId<br>
                      <strong>Secure Messaging</strong> &rarr; deviceID<br>
                      <strong>SMS</strong> &rarr; PhoneNumber<br>
                      <strong>TAP</strong> &rarr; PagerId<br>
                      <strong>Vocera</strong> &rarr; LoginID<br>"
            />
        </div>
    </div>

    @if(is_null($searchResults))
    @elseif(count($searchResults) === 0)
        <x-alert-info title="No Results" description="No results found for your search. Please try again." class="mt-4" />
    @elseif(count($searchResults) > 0)
        <div class="block w-full min-w-full">
            <div class="block bg-surface rounded border border-border shadow space-y-2 w-full my-4 py-4">
                <div class="px-4">
                    <div class="sm:flex sm:items-center">
                        <div class="sm:flex-auto">
                            <h1 class="text-base font-semibold leading-6 text-surface-fg">Search Results</h1>
                            <p class="mt-2 text-sm text-surface-fg-soft">
                                Results found for your Contact Method search.
                            </p>
                        </div>
                    </div>
                    <div class="mt-8 flow-root">
                        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <table class="min-w-full divide-y divide-border">
                                    <thead>
                                    <tr>
                                        <th scope="col" class="pr-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Method Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Directory Subject</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">View</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-surface-fg">Match</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-soft">
                                    @foreach($searchResults as $result)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-surface-fg sm:pl-0">
                                                {{ $result->MethodName ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {{ $result->DirectorySubject ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {{ $result->View ?? '' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-muted">
                                                {!!  Str::replace($this->searchQuery, "<strong class=\"text-primary\">{$this->searchQuery}</strong>",$result->Result ?? '') !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
