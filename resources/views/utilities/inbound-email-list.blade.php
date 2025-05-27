@php
    use Illuminate\Support\Facades\Auth;
@endphp
<div class="inline-flex w-full py-2 px-4 mx-auto my-8">

    <div class="flex flex-col  w-full">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">

            <h3 class="font-semibold text-2xl my-2 0 my-4">Received Emails</h3>

                <div class="table min-w-full divide-y divide-gray-200 shadow border border-gray-300  sm:rounded-lg">
                    <div class="bg-gray-50   table-row-group">

                        <div class="table-row">
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                                Processed
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                                Subject
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                                From
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                                To
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 0 uppercase tracking-wider">
                                Status
                            </div>

                        </div>

                        @foreach($emails as $email )

                            <div class="bg-white group
                                                 hover:bg-gray-100 transform transition duration-500 ease-in-out table-row ">
                                <div class="table-cell px-6 py-4 whitespace-nowrap text-xs font-semibold text-gray-700">

                                    {{ $email->created_at->timezone(Auth::user()->timezone)->format('m/d/Y g:i:s A T') }}

                                </div>
                                <div class="table-cell px-6 py-4 text-sm font-medium text-gray-700">
                                    <livewire:open-email-button :email="$email" :wire.key="$email->id"/>
                                </div>
                                <div class="table-cell px-6 py-4 whitespace-nowrap text-xs font-normal text-gray-700">

                                    {{ $email->from }}

                                </div>
                                <div class="table-cell px-6 py-4 text-xs font-normal text-gray-700">
                                   {{ $email->to  }}
                                </div>

                                <div class="table-cell px-6 py-4 whitespace-nowrap text-sm font-normal text-gray-700">
                                    @if($email->processed_at)

                                        <span class="px-2 inline-flex text-xs leading-5 rounded bg-green-600 text-white border border-green-500">
                                         Processed
                                        </span>

                                    @elseif($email->ignored_at)

                                        <span class="px-2 inline-flex text-xs leading-5 rounded bg-steel-400 text-steel-100 border border-steel-200">
                                         Ignored
                                        </span>

                                    @else

                                        <span class="px-2 inline-flex text-xs leading-5 font-normal rounded bg-red-600 text-white border border-red-500">
                                          Pending
                                        </span>

                                    @endif

                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
