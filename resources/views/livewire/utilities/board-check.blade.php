<div class="w-full">
    <div class="block px-2 py-4 mx-2">
        @include('utilities.board-nav')
    </div>
    <div class="container w-full flex">
        <div class="mx-2">

            <x-button
                wire:click="getRecent"
                class="px-2 py-1 transition transform duration-700 ease-in-out"
                    type="button">
                 <span
                     wire:loading.remove
                     wire:target="getRecent">
                     Fill Records
                </span>
                <span
                    wire:loading
                    wire:target="getRecent">
                    Filling...
                </span>

            </x-button>
        </div>

        @if( request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') ||
            request()->user()->hasTeamRole(request()->user()->currentTeam, 'manager') ||
            request()->user()->hasTeamRole(request()->user()->currentTeam, 'supervisor')
        )
            <div class="mx-2">

                <x-secondary-button
                    wire:click="clearRecords"
                    wire:loading.attr="disabled"
                    class="transition transform duration-700 ease-in-out">
                <span
                    wire:loading.remove
                    wire:target="clearRecords">
                    Clear Records
                </span>
                    <span
                        wire:loading
                        wire:target="clearRecords">
                    Clearing...
                </span>
                </x-secondary-button>

            </div>
        @endif


    </div>


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
            </tr>
            </thead>
            <tbody class="bg-white  divide-y divide-gray-200 ">
                @foreach($boardChecks as $row)
                    <tr class="group  transform transition duration-700 ease-in-out  ">
                        <td class="px-6 py-4 whitespace-nowrap text-sm transform transition duration-700 ease-in-out">
                            {{ $row->msgId }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm transform transition duration-700 ease-in-out">
                           {{ $row->callId }}
                        </td>

                        <td>
                            <span class="text-xs px-2 py-1 rounded shadow bg-yellow-100 border border-yellow-400  text-yellow-500">Needs Reviewed</span>
                        </td>

                        <td class="flex my-2">

                            <span class="inline-flex shadow rounded-md">
                                <a wire:click="$dispatch('openModal',{ component: 'utilities.board-dispatcher-review-message', arguments: {{ json_encode(['msgId' => $row->msgId, 'isCallID' => $row->callId]) }}  })"

                                    class="cursor-pointer -ml-px relative inline-flex items-center px-2 py-1 rounded-md border border-gray-300    bg-white text-sm text-gray-700   hover:bg-gray-50 focus:z-10 focus:outline-hidden focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                    <svg class="w-3 h-3 m-0.5 text-indigo-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2h-1.528A6 6 0 004 9.528V4z"></path><path fill-rule="evenodd" d="M8 10a4 4 0 00-3.446 6.032l-1.261 1.26a1 1 0 101.414 1.415l1.261-1.261A4 4 0 108 10zm-2 4a2 2 0 114 0 2 2 0 01-4 0z" clip-rule="evenodd"></path></svg>
                                    Review Message
                                </a>
                            </span>
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
            <x-alert-success title="Nothing to do!" description="There are no un-checked messages." />
        </div>
    @endif
</div>
