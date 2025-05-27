<div class="p-4 w-full">
    <div class="inline-flex min-w-full p-4 mx-auto mb-4 bg-indigo-100 border border-indigo-500 rounded text-indigo-900">
        <div class="flex">
            <div class="shrink-0">
                <svg class="h-5 w-5 " xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
        <div class="block ml-3">
            <h3 class="text-sm font-semibold">
                Special Process Required
            </h3>
            <div class="mt-2 text-sm">
                <p>
                    This integration allows TBS users to export a custom report (created by TBS) and process customer credit cards utilizing the Stripe API.
                    The general steps are outlined below:
                </p>
                <ul class="my-2 list-inside list-disc">
                    <li class="my-1">Add a new field to customer profiles in TBS to track <strong><a class="hover:underline" href="https://stripe.com/docs/api/customers">Stripe Customer ID</a></strong></li>
                    <li class="my-1">Ask TBS to create a custom report that exports client balances and the new <strong>Stripe Customer ID</strong> field</li>
                    <li class="my-1">Upload the report to this page and review the amounts to be billed.</li>
                    <li class="my-1">Run the live or test mode billing in minutes.</li>
                    <li class="my-1">Import the generated file using <strong>AutoDepC.exe</strong> provided by TBS.</li>
                </ul>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="my-4 py-4 px-2 bg-gray-100 border border-gray-300 shadow rounded">

        <input name="tbsExportFile" type="file" wire:model="tbsExportFile" class="text-gray-500 ">

        @if(session()->has('utilities.card-processing.process_results'))
            <x-secondary-button wire:loading.attr="disabled" wire:click.prevent="downloadExportFile">
                Download TBS Import File
            </x-secondary-button>
        @endif

        @if(session()->has('utilities.card-processing.import_file'))
            <x-secondary-button wire:click="clearSession" wire:loading.attr="disabled"
               class="float-right">
                Clear Current File
            </x-secondary-button>
        @else
            <x-button wire:loading.attr="disabled"  type="submit">
                Upload File
            </x-button>
        @endif

        @error('tbsExportFile')
        <span class="error block my-2 text-red-700">{{ $message }}</span>
        @enderror

    </form>

    @if($records)
        <table class="min-w-full text-left">
            <thead class="text-gray-500 uppercase">
            <tr class="sticky top-0">
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[0] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[1] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[2] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[3] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[4] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[5] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[6] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">{{ $headers[7] }}</th>
                <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Processed</th>

            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-white ">
            @php
                $stats['records_to_process'] = 0;
                $stats['amount_to_process'] = 0;

                $fmt = new NumberFormatter( 'en_US', NumberFormatter::CURRENCY );
                $curr = 'USD';
            @endphp

            @foreach($records as $recordId => $row)

               @if(strlen($row[5]) && $fmt->parseCurrency($row[7], $curr) > 0)
                   @php
                       $stats['records_to_process'] += 1;
                       $stats['amount_to_process'] += $fmt->parseCurrency($row[7], $curr);
                @endphp
                    <tr class="group bg-blue-200 font-semibold text-blue-800 ">
                @else
                    <tr class="group bg-gray-100 text-gray-500">
                @endif

                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[0] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[1] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[2] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[3] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[4] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[5] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $row[6] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $fmt->parseCurrency($row[7], $curr) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $this->records[$recordId]['processed'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <dl class="rounded-lg shadow sm:grid sm:grid-cols-3 mx-auto my-8 ">
            <div class="flex flex-col bg-gray-800 p-6 text-center">
                <dt class="order-2 mt-2 text-lg leading-6 font-medium text-gray-200 ">
                    Records
                </dt>
                <dd class="order-1 text-4xl font-bold text-blue-500">
                    {{ $stats['records_to_process'] }}
                </dd>
            </div>
            <div class="flex flex-col bg-gray-800 p-6 text-center">
                <dt class="order-2 mt-2 text-lg leading-6 font-medium text-gray-200 ">
                    Dollar Amount
                </dt>
                <dd class="order-1 text-3xl font-extrabold text-green-500">
                    {{ $fmt->formatCurrency($stats['amount_to_process'], $curr) }}
                </dd>
            </div>
            <div class="flex flex-col bg-gray-800 p-6 text-center">
                <dt class="order-2 mt-2 text-lg leading-6 font-medium text-gray-500 ">
                    <small class="text-xs text-white block mb-2">Batch Size: 10</small>
                    @if(strlen(decrypt($datasource->stripe_test_secret_key)) )
                        <x-secondary-button onclick="confirm('Run test mode?') || event.stopImmediatePropagation()" wire:click="processCardsSmallBatch(false)" wire:loading.attr="disabled" wire:loading.class="bg-red-500">
                            <span class="whitespace-nowrap">Test Mode</span>
                        </x-secondary-button>

                    <div wire:loading class="text-yellow-300 block">
                        Running...
                    </div>

                    @error('processing')
                    <span class="text-red-500">{{ $errors->first('processing') }}</span>
                    @enderror
                    @endif
                </dt>
                <dd class="order-1 text-5xl font-extrabold text-indigo-600">

                    @if(strlen(decrypt($datasource->stripe_prod_secret_key)))
                        <x-button onclick="confirm('Run LIVE mode?') || event.stopImmediatePropagation()" wire:click="processCardsSmallBatch(true)" wire:loading.class="disabled">
                            Live Mode
                        </x-button>
                        @error('processing')
                        <span class="text-red-500">{{ $errors->first('processing') }}</span>
                        @enderror
                    @endif

                </dd>
            </div>

        </dl>

    @endif

    @if($processResults)

        @if(isset($processResults['charges']) && count($processResults['charges']) > 0)
            <table class="min-w-full  text-left mb-8">
                <thead class="text-gray-500 uppercase">
                <tr class="sticky top-0">
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Client</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Account</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">PaymentID</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Status</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Amount</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Description</th>

                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-white ">
            @foreach($processResults['charges'] as $successfulCharge)
                @php
                    if(!is_array($successfulCharge)){
                        $successfulCharge = json_decode($successfulCharge, true);
                    }

                    $fmt = numfmt_create( 'en_US', NumberFormatter::CURRENCY );
                    $arr = collect(array_filter($this->records, function($k){
                        if(strlen($k[5]))
                        {
                            return true;
                        }
                        return false;
                    }));

                    $filtered = $arr->where(5, $successfulCharge['description'] ?? '') ;
                    $record = $filtered->first();
                @endphp

                <tr>
                    <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $record[3] ?? '0000' }}</td>
                    <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $record[4] ?? 'Unknown' }}</td>
                    <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $successfulCharge['id'] ?? 'Unknown' }}</td>
                    <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ ucwords($successfulCharge['status'] ?? 'Unknown') }}</td>
                    @if(isset($successfulCharge['amount']))
                        <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $fmt->formatCurrency($successfulCharge['amount']/100, 'USD' ) }}</td>
                    @else
                        <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $fmt->formatCurrency(0, 'USD' ) }}</td>
                    @endif

                    <td class="px-6 py-4 bg-green-200 text-green-800 whitespace-nowrap text-sm font-semibold">{{ $successfulCharge['description'] ?? ''}}</td>
                </tr>
            @endforeach
            </tbody>
            </table>
        @endif

        @if(isset($processResults['failures']) && count($processResults['failures']) > 0)
            <table class="min-w-full  text-left mb-8">
                <thead class="text-gray-500 uppercase">
                <tr class="sticky top-0">
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">BlCy</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Ofc</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">RefCode</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Account</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Name</th>
                    <th scope="col" class="px-6 py-3 text-sm font-semibold whitespace-nowrap">Amount</th>
                    <th scope="col" class="ppx-6 py-3 text-sm font-semibold whitespace-nowrap">Error</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-white ">
            @foreach($processResults['failures'] as $failedCharge)
                @php
                    if(!is_array($failedCharge)){
                           $failedCharge = json_decode($failedCharge, true);
                       }
                @endphp
                <tr class="group bg-red-200 text-red-800 font-semibold ">
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[0] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[1] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[2] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[3] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[4] }}<br><small>{{ $failedCharge[5] }}</small></td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge[7] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $failedCharge['results'] ?? '' }}</td>
                </tr>

            @endforeach
                </tbody>
            </table>
        @endif
    @endif

</div>
