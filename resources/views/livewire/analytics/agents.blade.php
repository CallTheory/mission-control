@php
    use App\Models\Stats\Helpers;
@endphp
<div class="w-full my-2 inline-block">
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="my-2">
                    {{ $agents->links() }}
                </div>
                <div class="shadow border-b border-gray-300  sm:rounded-lg">




                    <table class="min-w-full divide-y divide-gray-200  text-center">
                        <thead class="">
                        <tr class="sticky top-0">
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">agtId</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Name</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Initials</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Soft Agent Application</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Supervisor Application</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Locked Out</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Calls</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Dispatches</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Dials</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Dial Duration</th>

                        </tr>
                        </thead>
                        <tbody class="bg-white  divide-y divide-gray-200 ">
                        @foreach($agents as $agent)
                            <tr class="group  transform transition duration-700 ease-in-out">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->agtId }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    <a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out" href="/analytics/agent-performance/{{ $agent->Name }}">
                                        {{ $agent->Name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->Initials }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ Helpers::formatDuration($agent->AgentDuration) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ Helpers::formatDuration($agent->SuperDuration) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->LockedOut }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->Calls }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->Dispatches }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ $agent->Dials }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {{ Helpers::formatDuration($agent->DialDur) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="my-2">
                    {{ $agents->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
