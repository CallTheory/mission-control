@php
    use Illuminate\Support\Facades\Auth;
@endphp
<div class="inline-flex w-full py-2 px-4 mx-auto my-8">

    <div class="flex flex-col  w-full">
        {{-- Same as the rules table above: no negative margins, or the list
             overhangs the card it sits in. --}}
        <div class="overflow-x-auto">
            <div class="py-2 align-middle inline-block min-w-full">

            <h3 class="font-semibold text-2xl my-2 0 my-4">Received Emails</h3>

                <div class="table min-w-full divide-y divide-border-soft shadow border border-border sm:rounded-lg">
                    <div class="bg-surface-2 table-row-group">

                        <div class="table-row">
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                                Processed
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                                Subject
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                                From
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                                To
                            </div>
                            <div scope="col" class="table-cell px-6 py-3 text-left text-xs font-medium text-muted 0 uppercase tracking-wider">
                                Status
                            </div>

                        </div>

                        @foreach($emails as $email )

                            <div class="bg-surface group
                                                 hover:bg-surface-2 transform transition duration-500 ease-in-out table-row ">
                                <div class="table-cell px-6 py-4 whitespace-nowrap text-xs font-semibold text-surface-fg-soft">

                                    {{ $email->created_at->timezone(Auth::user()->timezone)->format('m/d/Y g:i:s A T') }}

                                </div>
                                <div class="table-cell px-6 py-4 text-sm font-medium text-surface-fg-soft">
                                    <livewire:open-email-button :email="$email" :wire.key="$email->id"/>
                                </div>
                                <div class="table-cell px-6 py-4 whitespace-nowrap text-xs font-normal text-surface-fg-soft">

                                    {{ $email->from }}

                                </div>
                                <div class="table-cell px-6 py-4 text-xs font-normal text-surface-fg-soft">
                                   {{ $email->to  }}
                                </div>

                                <div class="table-cell px-6 py-4 whitespace-nowrap text-sm font-normal text-surface-fg-soft">
                                    @if($email->processed_at)

                                        <span class="px-2 inline-flex text-xs leading-5 rounded bg-success text-success-fg border border-success">
                                         Processed
                                        </span>

                                    @elseif($email->ignored_at)

                                        <span class="px-2 inline-flex text-xs leading-5 rounded bg-surface-3 text-surface-fg border border-border">
                                         Ignored
                                        </span>

                                    @else

                                        <span class="px-2 inline-flex text-xs leading-5 font-normal rounded bg-danger text-danger-fg border border-danger">
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
