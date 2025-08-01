@php
    use App\Models\Stats\Helpers;
    $boardCheckCategories = Helpers::boardCheckCategories();

@endphp
<div class="w-full">
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>

    @if( request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') ||
        request()->user()->hasTeamRole(request()->user()->currentTeam, 'manager') ||
        request()->user()->hasTeamRole(request()->user()->currentTeam, 'supervisor')
    )
        <div class="inline my-2">
            <form wire:target="exportPeopleSoft" wire:submit="exportPeopleSoft" class="mx-4 my-4">
                <button class="cursor-pointer px-3 py-2 bg-gray-800 hover:bg-gray-600 text-white shadow rounded-lg transition transform duration-700 ease-in-out" type="submit">
                    <div class="" wire:loading>Exporting...</div>
                    <div wire:loading.remove>Export To PeoplePraise</div>
                </button>
            </form>
        </div>

    @endif

@if($boardChecks->count())
        <div class="my-2">
            {{ $boardChecks->links() }}
        </div>
        <table class="min-w-full divide-y divide-gray-200  text-left">
            <thead class="">
            <tr class="sticky top-0">
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">msgId</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">isCallId</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Status</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Actions</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Category</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Comments</th>
                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Last Update</th>
            </tr>
            </thead>
            <tbody class="bg-white  divide-y divide-gray-200 ">
            @foreach($boardChecks as $row)
                <tr class="group  transform transition duration-700 ease-in-out  text-gray-500">
                    <td class="px-6 py-4 whitespace-nowrap text-sm transform transition duration-700 ease-in-out">
                        {{ $row->msgId }}

                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm transform transition duration-700 ease-in-out">
                        <a class="flex text-indigo-500 hover:text-indigo-700 font-semibold" target="_blank" href="/utilities/call-lookup/{{ $row->callId }}">
                            <svg class="w-4 h-4 m-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"></path></svg>
                            {{ $row->callId }}
                        </a>
                    </td>
                    <td class="whitespace-nowrap">
                        @if(is_null($row->marked_ok_at) && is_null($row->problem_verified_at))
                            <span class="text-xs px-2 py-1 rounded shadow bg-yellow-400 text-yellow-800">Needs Reviewed</span>
                        @elseif($row->marked_ok_at)
                            <span class="text-xs px-2 py-1 rounded shadow bg-green-400 text-green-800" title="Approved by {{ $row->marked_ok_by }} at {{ $row->marked_ok_at }}">
                                Supervisor OK
                            </span>
                        @elseif($row->problem_verified_at)
                            <span class="text-xs px-2 py-1 rounded shadow bg-red-400 text-red-800" title="Verified by {{ $row->problem_verified_by }} at {{ $row->problem_verified_at }}">
                                Problem Verified
                            </span>
                        @elseif($row->approved_at)
                            <span class="text-xs px-2 py-1 rounded shadow bg-green-400 text-green-800">Dispatcher OK</span>
                        @elseif($row->problem_found_at)
                            <span class="text-xs px-2 py-1 rounded shadow bg-red-400 text-red-800">Flagged by Dispatcher</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded shadow bg-gray-400 text-gray-800">Unknown Status</span>
                        @endif

                    </td>
                    <td class="flex my-2">
                        <span class=" inline-flex shadow rounded-md">
                            <a  wire:click="$dispatch('openModal', { component: 'utilities.board-supervisor-review-message', arguments: {{ json_encode(['msgId' => $row->msgId, 'isCallID' => $row->callId]) }} })" class="cursor-pointer -ml-px relative inline-flex items-center px-2 py-1 rounded-md border border-gray-300    bg-white text-sm text-gray-700   hover:bg-gray-50 focus:z-10 focus:outline-hidden focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <svg class="w-3 h-3 m-0.5 text-indigo-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2h-1.528A6 6 0 004 9.528V4z"></path><path fill-rule="evenodd" d="M8 10a4 4 0 00-3.446 6.032l-1.261 1.26a1 1 0 101.414 1.415l1.261-1.261A4 4 0 108 10zm-2 4a2 2 0 114 0 2 2 0 01-4 0z" clip-rule="evenodd"></path></svg>
                                 Details
                            </a>
                        </span>
                    </td>
                    <td class="text-sm">{{ $boardCheckCategories[$row->category] ?? '' }}</td>
                    <td class="break-words text-ellipsis" title="{{ $row->comments ?? 'No Comments' }}">
                        {{ $row->comments ?? '' }}
                    </td>
                    <td class="text-ellipsis text-xs" title="{{ $row->updated_at->timezone(Auth::user()->timezone)->format('m/d/Y g:i:s A T') }}">
                        {{ $row->updated_at->timezone(Auth::user()->timezone)->format('m/d/Y g:i:s A T') }}

                        <small class="block 0">
                            @if( $row->marked_ok_by)
                                {{ $row->marked_ok_by }}
                            @else
                                {{ $row->problem_verified_by }}
                            @endif
                        </small>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="my-2">
            {{ $boardChecks->links() }}
        </div>


    @else
    <div class="mx-4">
        <x-alert-success title="Nothing to do!" description="There are no board check items." />
    </div>
    @endif
</div>
