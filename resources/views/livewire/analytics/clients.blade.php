<div class="w-full my-2 inline-block">
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">

                @if($clients)

                    <div class="my-2">
                        {{ $clients->links() }}
                    </div>
                    <div class="shadow overflow-hidden border-b border-gray-300  sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200  text-left">
                        <thead class="">
                            <tr class="sticky top-0">
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">cltId</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Client Number</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Billing Code</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Client Name</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Sources</th>
                                <!--
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Directory Subject</th>
                                -->
                            </tr>
                        </thead>
                            <tbody class="bg-white  divide-y divide-gray-200 ">

                            @foreach($clients as $client)
                                <tr class="group  transform transition duration-700 ease-in-out">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">{{ $client->cltId }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">{{ $client->ClientNumber }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">{{ $client->BillingCode }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out"><a class="hover:cursor-pointer hover:underline transform transition duration-700 ease-in-out" href="/analytics/client-accounts/{{ $client->ClientNumber }}">{{ $client->ClientName }}</a></td>
                                    <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                        @foreach($sources as $source)
                                            @if($source->cltId === $client->cltId)
                                                <span class="inline-block my-0.5 mx-1 px-1 py-0.5 border   transform transition duration-700 ease-in-out  font-hairline text-white border-indigo-700 bg-indigo-700 rounded shadow">{{ $source->Source }}</span>
                                            @endif
                                        @endforeach
                                    </td>
                                    <!--
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">{{ $client->Directory }}</td>
                                    -->
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
